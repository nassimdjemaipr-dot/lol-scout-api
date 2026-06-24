<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Player;
use App\Entity\RiotAccount;
use App\Entity\User;
use App\Enum\PlayerRole;
use App\Enum\UserRole;
use App\Service\RiotSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RiotSyncServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    // ─── linkRiotAccount : validations ─────────────────────────

    public function testLinkRiotAccountWithInvalidRegionThrows(): void
    {
        $service = $this->makeService(new MockHttpClient([]));
        $player = $this->createTestPlayer('invalid-region');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Region "ZZZ" invalide');

        $service->linkRiotAccount($player, 'TestUser#EUW', 'ZZZ');
    }

    public function testLinkRiotAccountSuccessfullyResolvesPuuid(): void
    {
        $puuid = 'fake-puuid-' . bin2hex(random_bytes(8));
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'puuid' => $puuid,
                'gameName' => 'TestPlayer',
                'tagLine' => 'EUW',
            ]), ['http_code' => 200]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('link-ok');

        $riotAccount = $service->linkRiotAccount($player, 'TestPlayer#EUW', 'EUW1');

        $this->assertInstanceOf(RiotAccount::class, $riotAccount);
        $this->assertSame($puuid, $riotAccount->getPuuid());
        $this->assertSame('EUW1', $riotAccount->getRegion());
        $this->assertSame('TestPlayer#EUW', $riotAccount->getSummonerName());
    }

    public function testLinkRiotAccountWithoutTagUsesRegionAsTag(): void
    {
        $puuid = 'fake-puuid-' . bin2hex(random_bytes(8));
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['puuid' => $puuid]), ['http_code' => 200]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('link-notag');

        $riotAccount = $service->linkRiotAccount($player, 'NoTagPlayer', 'EUW1');
        $this->assertStringContainsString('#EUW1', $riotAccount->getSummonerName());
    }

    // ─── linkRiotAccount : erreurs Riot ─────────────────────────

    public function testLinkRiotAccountWith401Throws(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 401]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('link-401');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 401/');

        $service->linkRiotAccount($player, 'X#EUW', 'EUW1');
    }

    public function testLinkRiotAccountWith404Throws(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('link-404');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');

        $service->linkRiotAccount($player, 'X#EUW', 'EUW1');
    }

    public function testLinkRiotAccountWith429Throws(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 429]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('link-429');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Quota Riot/');

        $service->linkRiotAccount($player, 'X#EUW', 'EUW1');
    }

    public function testLinkRiotAccountWith500Throws(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('link-500');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Erreur cote Riot|HTTP 500/');

        $service->linkRiotAccount($player, 'X#EUW', 'EUW1');
    }

    public function testLinkRiotAccountWithMissingPuuidThrows(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode(['gameName' => 'X']), ['http_code' => 200]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('link-nopuuid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/PUUID manquant/');

        $service->linkRiotAccount($player, 'X#EUW', 'EUW1');
    }

    // ─── syncStats ──────────────────────────────────────────────

    public function testSyncStatsWithRankedData(): void
    {
        // Reponses dans l'ordre des appels : league/v4, champion-mastery/v4, ddragon versions, ddragon champion.json
        $httpClient = new MockHttpClient([
            // 1. league-v4 entries (rang Solo/Duo)
            new MockResponse(json_encode([
                [
                    'queueType' => 'RANKED_SOLO_5x5',
                    'tier' => 'GOLD',
                    'rank' => 'II',
                    'wins' => 50,
                    'losses' => 30,
                ],
            ]), ['http_code' => 200]),
            // 2. champion-mastery-v4 top
            new MockResponse(json_encode([
                ['championId' => 64, 'championPoints' => 100000],
                ['championId' => 157, 'championPoints' => 50000],
            ]), ['http_code' => 200]),
            // 3. ddragon versions
            new MockResponse(json_encode(['15.10.1']), ['http_code' => 200]),
            // 4. ddragon champion data
            new MockResponse(json_encode([
                'data' => [
                    'LeeSin' => ['key' => '64', 'name' => 'Lee Sin'],
                    'Yasuo'  => ['key' => '157', 'name' => 'Yasuo'],
                ],
            ]), ['http_code' => 200]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('sync-stats');

        // On cree directement un RiotAccount (pas de linkRiotAccount necessaire ici)
        $riotAccount = (new RiotAccount())
            ->setPuuid('puuid-' . bin2hex(random_bytes(8)))
            ->setPlayer($player)
            ->setSummonerName('SyncPlayer#EUW')
            ->setRegion('EUW1');
        $this->em->persist($riotAccount);
        $this->em->flush();

        $stats = $service->syncStats($riotAccount);

        $this->assertSame('Gold II', $stats->getTier());
        $this->assertSame(80, $stats->getRankedGamesCount());
        $this->assertSame('62.5', $stats->getWinrate());
    }

    public function testSyncStatsWithoutRankedReturnsUnranked(): void
    {
        $httpClient = new MockHttpClient([
            // Pas de queue RANKED_SOLO_5x5 dans les entries
            new MockResponse(json_encode([]), ['http_code' => 200]),
            new MockResponse(json_encode([]), ['http_code' => 200]),
            // ddragon
            new MockResponse(json_encode(['15.10.1']), ['http_code' => 200]),
            new MockResponse(json_encode(['data' => []]), ['http_code' => 200]),
        ]);

        $service = $this->makeService($httpClient);
        $player = $this->createTestPlayer('sync-unranked');

        $riotAccount = (new RiotAccount())
            ->setPuuid('puuid-' . bin2hex(random_bytes(8)))
            ->setPlayer($player)
            ->setSummonerName('Unranked#EUW')
            ->setRegion('EUW1');
        $this->em->persist($riotAccount);
        $this->em->flush();

        $stats = $service->syncStats($riotAccount);

        $this->assertSame('Unranked', $stats->getTier());
        $this->assertSame(0, $stats->getRankedGamesCount());
    }

    // ─── Helpers ────────────────────────────────────────────────

    /**
     * Construit un RiotSyncService avec un HttpClient mocke.
     * Les autres dependances (EM, cache, logger) viennent du container test.
     */
    private function makeService(MockHttpClient $httpClient): RiotSyncService
    {
        $cache = static::getContainer()->get('cache.app');
        $logger = static::getContainer()->get('logger');

        // On reset le cache pour qu'il ne contienne pas un PUUID/champion d'un test precedent
        $cache->clear();

        return new RiotSyncService(
            $httpClient,
            $this->em,
            $cache,
            $logger,
            'fake-test-api-key'
        );
    }

    /**
     * Cree un user + player en BDD test, retourne le Player.
     */
    private function createTestPlayer(string $emailPrefix): Player
    {
        $email = $emailPrefix . '-' . bin2hex(random_bytes(6)) . '@test.local';

        $user = new User();
        $user->setEmail($email);
        $user->setPassword('$2y$10$abcdefghijklmnopqrstuv'); // hash bidon (pas utilise)
        $user->setRole(UserRole::PLAYER);
        $this->em->persist($user);

        $player = new Player();
        $player->setUser($user);
        $player->setPseudo('TestPseudo' . bin2hex(random_bytes(2)));
        $player->setFirstName('Test');
        $player->setLastName('Player');
        $player->setGameRole(PlayerRole::MID);
        $player->setIsAvailable(true);
        $this->em->persist($player);

        $this->em->flush();

        return $player;
    }
}