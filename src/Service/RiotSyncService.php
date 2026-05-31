<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlayedChampion;
use App\Entity\Player;
use App\Entity\PlayerStats;
use App\Entity\RiotAccount;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service d'intégration avec l'API Riot Games.
 *
 * Deux opérations principales :
 *  - linkRiotAccount() : résout un Riot ID (gameName#tagLine) en PUUID
 *    et crée le RiotAccount associé au joueur.
 *  - syncStats() : récupère le rang Solo/Duo, le winrate et les 3 champions
 *    les plus joués, puis met à jour PlayerStats et PlayedChampion.
 *
 * Le PUUID est mis en cache 1h pour éviter de re-consommer le quota Riot
 * sur des résolutions répétées du même summoner.
 */
class RiotSyncService
{
    /**
     * Mapping plateforme (region en jeu) → regional router pour Account-V1.
     * Account-V1 ne fonctionne PAS sur les endpoints plateforme,
     * il faut passer par les routeurs régionaux (europe/americas/asia/sea).
     */
    private const REGION_ROUTING = [
        'EUW1' => 'europe',
        'EUN1' => 'europe',
        'RU'   => 'europe',
        'TR1'  => 'europe',
        'ME1'  => 'europe',
        'NA1'  => 'americas',
        'BR1'  => 'americas',
        'LA1'  => 'americas',
        'LA2'  => 'americas',
        'JP1'  => 'asia',
        'KR'   => 'asia',
        'OC1'  => 'sea',
        'PH2'  => 'sea',
        'SG2'  => 'sea',
        'TH2'  => 'sea',
        'TW2'  => 'sea',
        'VN2'  => 'sea',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly string $riotApiKey,
    ) {
    }


    /**
     * Lie un compte Riot à un joueur LoL Scout.
     * Le summonerName doit être au format "gameName#tagLine" (Riot ID).
     */
    public function linkRiotAccount(Player $player, string $summonerName, string $region): RiotAccount
    {
        $region = strtoupper($region);
        if (!isset(self::REGION_ROUTING[$region])) {
            throw new \InvalidArgumentException(sprintf('Region "%s" invalide. Valeurs autorisées : %s', $region, implode(', ', array_keys(self::REGION_ROUTING))));
        }

        // Le Riot ID actuel est "gameName#tagLine". Si l'utilisateur n'a pas
        // de #, on assume que tagLine = region (cas fallback).
        if (str_contains($summonerName, '#')) {
            [$gameName, $tagLine] = explode('#', $summonerName, 2);
        } else {
            $gameName = $summonerName;
            $tagLine = $region;
        }

        // Étape 1 : résoudre le PUUID via le regional router
        $puuid = $this->resolvePuuid($gameName, $tagLine, $region);

        // Étape 2 : récupérer le RiotAccount existant du joueur (un joueur = 1 seul compte Riot)
        // Si le joueur a déjà un compte (ex. créé par les fixtures), on le METTRE À JOUR
        // au lieu d'en créer un nouveau, pour garder les PlayerStats / PlayedChampion liés.
        $riotAccount = $this->em->getRepository(RiotAccount::class)
            ->findOneBy(['player' => $player]);

        // Sécurité : vérifier que ce PUUID n'est pas déjà lié à un AUTRE joueur
        $conflicting = $this->em->getRepository(RiotAccount::class)
            ->findOneBy(['puuid' => $puuid]);
        if ($conflicting !== null && $conflicting !== $riotAccount) {
            throw new \RuntimeException('Ce compte Riot est déjà lié à un autre joueur LoL Scout.');
        }

        if ($riotAccount === null) {
            $riotAccount = new RiotAccount();
        }

        $riotAccount
            ->setPuuid($puuid)
            ->setPlayer($player)
            ->setSummonerName($gameName.'#'.$tagLine)
            ->setRegion($region);

        $this->em->persist($riotAccount);
        $this->em->flush();

        return $riotAccount;
    }


    /**
     * Synchronise les stats Riot pour un compte donné.
     * Crée ou met à jour le PlayerStats et les PlayedChampion[].
     */
    public function syncStats(RiotAccount $riotAccount): PlayerStats
    {
        $puuid = $riotAccount->getPuuid();
        $region = strtolower($riotAccount->getRegion());

        // Étape 1 : rang Solo/Duo (winrate, parties jouées)
        $rankData = $this->fetchRankedEntries($puuid, $region);

        // Étape 2 : top 3 champions joués
        $topChampions = $this->fetchTopChampions($puuid, $region);

        // Étape 3 : créer ou mettre à jour le PlayerStats
        $stats = $this->em->getRepository(PlayerStats::class)
            ->findOneBy(['riotAccount' => $riotAccount]);

        if ($stats === null) {
            $stats = new PlayerStats();
            $stats->setRiotAccount($riotAccount);
        }

        $wins = $rankData['wins'] ?? 0;
        $losses = $rankData['losses'] ?? 0;
        $totalGames = $wins + $losses;
        $winrate = $totalGames > 0 ? round(($wins / $totalGames) * 100, 2) : 0;

        $stats->setTier($rankData['tier_full'] ?? 'Unranked')
            ->setWinrate((string) $winrate)
            ->setRankedGamesCount($totalGames)
            // Les valeurs KDA/CS/vision ne sont pas fournies par league-v4.
            // On les laisse à 0 pour le MVP (post-MVP : Match-V5 pour les détails).
            ->setAverageKda('0.00')
            ->setCsPerMinute('0.00')
            ->setVisionScore('0.00');

        $this->em->persist($stats);

        // Étape 4 : supprimer les anciens PlayedChampion et les recréer
        foreach ($stats->getId() ? $this->em->getRepository(PlayedChampion::class)
            ->findBy(['playerStats' => $stats]) : [] as $old) {
            $this->em->remove($old);
        }

        foreach ($topChampions as $champ) {
            $playedChampion = new PlayedChampion();
            $playedChampion
                ->setPlayerStats($stats)
                ->setChampionName('Champion #'.$champ['championId'])
                ->setGamesPlayed($champ['championPoints'] ?? 0)
                ->setWinrate('0.00')
                ->setKda('0.00');
            $this->em->persist($playedChampion);
        }

        $riotAccount->setLastSyncAt(new \DateTimeImmutable());
        $this->em->persist($riotAccount);

        $this->em->flush();

        return $stats;
    }


    /* ───────────────────────────────────────────────────────── */
    /*  Méthodes privées : appels Riot                            */
    /* ───────────────────────────────────────────────────────── */

    /**
     * Résout un Riot ID (gameName#tagLine) en PUUID.
     * Cache 1h pour éviter de re-consommer le quota Riot.
     */
    private function resolvePuuid(string $gameName, string $tagLine, string $region): string
    {
        $routing = self::REGION_ROUTING[$region];
        $cacheKey = sprintf('riot.puuid.%s.%s.%s', $routing, strtolower($gameName), strtolower($tagLine));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($gameName, $tagLine, $routing): string {
            $item->expiresAfter(3600); // 1h

            $url = sprintf(
                'https://%s.api.riotgames.com/riot/account/v1/accounts/by-riot-id/%s/%s',
                $routing,
                rawurlencode($gameName),
                rawurlencode($tagLine)
            );

            $response = $this->callRiot($url);
            $data = $response->toArray();

            if (!isset($data['puuid'])) {
                throw new \RuntimeException('Réponse Riot inattendue : PUUID manquant');
            }

            return $data['puuid'];
        });
    }


    /**
     * Récupère les entrées de classement Solo/Duo pour un PUUID.
     * @return array{tier_full?: string, wins?: int, losses?: int, ...}
     */
    private function fetchRankedEntries(string $puuid, string $region): array
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/league/v4/entries/by-puuid/%s',
            strtolower($region),
            rawurlencode($puuid)
        );

        $response = $this->callRiot($url);
        $entries = $response->toArray();

        // On garde uniquement la queue "RANKED_SOLO_5x5" (Solo/Duo)
        foreach ($entries as $entry) {
            if (($entry['queueType'] ?? null) === 'RANKED_SOLO_5x5') {
                $entry['tier_full'] = sprintf('%s %s', ucfirst(strtolower($entry['tier'] ?? '')), $entry['rank'] ?? '');
                return $entry;
            }
        }

        return ['tier_full' => 'Unranked', 'wins' => 0, 'losses' => 0];
    }


    /**
     * Top 3 champions par mastery points.
     * @return list<array{championId: int, championPoints: int, ...}>
     */
    private function fetchTopChampions(string $puuid, string $region): array
    {
        $url = sprintf(
            'https://%s.api.riotgames.com/lol/champion-mastery/v4/champion-masteries/by-puuid/%s/top?count=3',
            strtolower($region),
            rawurlencode($puuid)
        );

        $response = $this->callRiot($url);
        return $response->toArray();
    }


    /**
     * Wrapper centralisé pour les appels Riot, avec gestion d'erreur lisible.
     */
    private function callRiot(string $url)
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'X-Riot-Token' => $this->riotApiKey,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 401 || $statusCode === 403) {
                throw new \RuntimeException('Clé API Riot invalide ou expirée (HTTP '.$statusCode.')');
            }
            if ($statusCode === 404) {
                throw new \RuntimeException('Ressource Riot introuvable (HTTP 404)');
            }
            if ($statusCode === 429) {
                throw new \RuntimeException('Quota Riot dépassé (HTTP 429). Réessaie dans quelques minutes.');
            }
            if ($statusCode >= 500) {
                throw new \RuntimeException('Erreur côté Riot (HTTP '.$statusCode.'). Réessaie plus tard.');
            }
            if ($statusCode !== 200) {
                throw new \RuntimeException('Réponse Riot inattendue (HTTP '.$statusCode.')');
            }

            return $response;
        } catch (TransportException $e) {
            $this->logger->error('Riot API transport error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Impossible de joindre l\'API Riot : '.$e->getMessage(), 0, $e);
        }
    }
}