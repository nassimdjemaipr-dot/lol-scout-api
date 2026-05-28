<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Application;
use App\Entity\Club;
use App\Entity\Offer;
use App\Entity\PlayedChampion;
use App\Entity\Player;
use App\Entity\PlayerStats;
use App\Entity\RiotAccount;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Enum\PlayerRole;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démo pour LoL Scout.
 * Mot de passe commun à tous les comptes : "password"
 *
 * Chargement : docker compose exec php php bin/console doctrine:fixtures:load
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // ─── ADMIN ──────────────────────────────────────────
        $admin = $this->createUser('admin@lolscout.gg', UserRole::ADMIN);
        $manager->persist($admin);

        // ─── JOUEURS ────────────────────────────────────────
        $playersData = [
            ['ShadowMid',   'Lucas',  'Martin',   PlayerRole::MID,     'Diamond II',  56.3, 142, ['Ahri', 'Syndra', 'Yasuo'], true],
            ['JungleKing',  'Hugo',   'Bernard',  PlayerRole::JUNGLE,  'Master I',    61.0, 230, ['Lee Sin', 'Elise', 'Graves'], true],
            ['TopDiff',     'Noah',   'Dubois',   PlayerRole::TOP,     'Diamond IV',  52.1, 98,  ['Darius', 'Garen', 'Sett'], true],
            ['ADCarry',     'Emma',   'Petit',    PlayerRole::ADC,     'Emerald I',   54.8, 176, ['Jinx', 'Caitlyn', 'Ezreal'], false],
            ['SupDiffAndy', 'Léa',    'Robert',   PlayerRole::SUPPORT, 'Platinum II', 49.2, 203, ['Thresh', 'Lulu', 'Nautilus'], true],
            ['FaastHands',  'Tom',    'Richard',  PlayerRole::MID,     'Grandmaster', 63.5, 312, ['Zed', 'Akali', 'LeBlanc'], true],
        ];

        $players = [];
        foreach ($playersData as $i => $data) {
            [$pseudo, $firstName, $lastName, $role, $tier, $winrate, $games, $champions, $available] = $data;

            $user = $this->createUser(sprintf('joueur%d@lolscout.gg', $i + 1), UserRole::PLAYER);
            $manager->persist($user);

            $player = new Player();
            $player->setUser($user)
                ->setPseudo($pseudo)
                ->setFirstName($firstName)
                ->setLastName($lastName)
                ->setGameRole($role)
                ->setIsAvailable($available)
                ->setBio(sprintf('Joueur %s passionné, %s sur EUW. À la recherche d\'une équipe sérieuse pour la prochaine saison.', $role->value, $tier));
            $manager->persist($player);

            // Compte Riot + stats associées
            $riotAccount = new RiotAccount();
            $riotAccount->setPlayer($player)
                ->setSummonerName($pseudo.'#EUW')
                ->setPuuid('demo-puuid-'.uniqid())
                ->setRegion('EUW1')
                ->setLastSyncAt(new \DateTimeImmutable());
            $manager->persist($riotAccount);

            $stats = new PlayerStats();
            $stats->setRiotAccount($riotAccount)
                ->setTier($tier)
                ->setWinrate((string) $winrate)
                ->setAverageKda((string) round(mt_rand(180, 420) / 100, 2))
                ->setCsPerMinute((string) round(mt_rand(550, 850) / 100, 2))
                ->setVisionScore((string) round(mt_rand(1500, 4500) / 100, 2))
                ->setRankedGamesCount($games);
            $manager->persist($stats);

            foreach ($champions as $champName) {
                $champ = new PlayedChampion();
                $champ->setPlayerStats($stats)
                    ->setChampionName($champName)
                    ->setGamesPlayed(mt_rand(20, 80))
                    ->setWinrate((string) round(mt_rand(4500, 6500) / 100, 2))
                    ->setKda((string) round(mt_rand(200, 450) / 100, 2));
                $manager->persist($champ);
            }

            $players[] = $player;
        }

        // ─── CLUBS ──────────────────────────────────────────
        $clubsData = [
            ['Phoenix Esport',  'Structure semi-pro fondée en 2023, on vise la LFL2.', true],
            ['Nova Gaming',     'Équipe amateur ambitieuse cherchant à monter en division.', true],
            ['Shadow Wolves',   'Collectif esport convivial, ambiance avant tout.', false],
        ];

        $clubs = [];
        foreach ($clubsData as $i => $data) {
            [$name, $description, $verified] = $data;

            $user = $this->createUser(sprintf('club%d@lolscout.gg', $i + 1), UserRole::CLUB);
            $manager->persist($user);

            $club = new Club();
            $club->setUser($user)
                ->setName($name)
                ->setDescription($description)
                ->setWebsite('https://'.strtolower(str_replace(' ', '', $name)).'.gg')
                ->setIsVerified($verified);
            $manager->persist($club);

            $clubs[] = $club;
        }

        // ─── OFFRES ─────────────────────────────────────────
        $offersData = [
            [0, 'Recherche MID Diamond+ pour roster compétitif', PlayerRole::MID,     'Diamond IV',  true],
            [0, 'JUNGLE Master minimum pour scrims quotidiens',   PlayerRole::JUNGLE,  'Master I',    true],
            [1, 'ADC Emerald+ motivé pour la saison',             PlayerRole::ADC,     'Emerald I',   true],
            [1, 'SUPPORT recherché — ambiance sérieuse',          PlayerRole::SUPPORT, 'Platinum I',  true],
            [2, 'TOP laner pour équipe amateur (closed)',         PlayerRole::TOP,     'Gold I',      false],
        ];

        $offers = [];
        foreach ($offersData as $data) {
            [$clubIndex, $title, $role, $minRank, $active] = $data;

            $offer = new Offer();
            $offer->setClub($clubs[$clubIndex])
                ->setTitle($title)
                ->setDescription(sprintf('Nous recherchons un joueur %s de niveau %s minimum, disponible en soirée pour les entraînements. Bonne mentalité exigée, esprit d\'équipe indispensable. Contacte-nous via la plateforme !', $role->value, $minRank))
                ->setWantedRole($role)
                ->setMinimumRank($minRank)
                ->setExpiresAt(new \DateTimeImmutable('+30 days'))
                ->setIsActive($active);
            $manager->persist($offer);

            $offers[] = $offer;
        }

        // ─── CANDIDATURES ───────────────────────────────────
        $applicationsData = [
            [0, 0, ApplicationStatus::EN_ATTENTE, 'Bonjour, je suis MID Diamond II avec 56% de winrate. Très motivé pour rejoindre un roster compétitif !'],
            [5, 0, ApplicationStatus::ACCEPTEE,   'Grandmaster MID, je cherche un projet sérieux. Disponible tous les soirs.'],
            [3, 2, ApplicationStatus::EN_ATTENTE, 'ADC Emerald I, mains Jinx/Caitlyn. Je peux faire un essai quand vous voulez.'],
            [4, 3, ApplicationStatus::REFUSEE,    'Support Platinum II, dispo en soirée pour les scrims.'],
            [1, 1, ApplicationStatus::EN_ATTENTE, 'Jungle Master I, 61% WR sur 230 games. Je connais bien la macro et le pathing.'],
        ];

        foreach ($applicationsData as $data) {
            [$playerIndex, $offerIndex, $status, $message] = $data;

            $application = new Application();
            $application->setPlayer($players[$playerIndex])
                ->setOffer($offers[$offerIndex])
                ->setMessage($message)
                ->setStatus($status);
            $manager->persist($application);
        }

        $manager->flush();
    }

    private function createUser(string $email, UserRole $role): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setRole($role);
        $user->setPassword($this->hasher->hashPassword($user, 'password'));

        return $user;
    }
}