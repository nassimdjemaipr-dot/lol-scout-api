<?php

declare(strict_types=1);

namespace App\Tests\Controller;

class AdminControllerTest extends ApiTestCase
{
    private const ENDPOINTS = [
        ['GET', '/api/admin/stats'],
        ['GET', '/api/admin/users'],
        ['GET', '/api/admin/clubs'],
    ];

    // ─── Contrôle d'accès ───────────────────────────────────────

    public function testEveryAdminEndpointRejectsAnonymousAccess(): void
    {
        foreach (self::ENDPOINTS as [$method, $path]) {
            $this->client->request($method, $path);
            $this->assertResponseStatusCodeSame(401, $path . ' devrait exiger une authentification');
        }
    }

    public function testEveryAdminEndpointRejectsAPlayer(): void
    {
        $token = $this->registerAndLogin('player-vs-admin', 'ROLE_PLAYER');

        foreach (self::ENDPOINTS as [$method, $path]) {
            $this->client->request($method, $path, [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]);
            $this->assertResponseStatusCodeSame(403, $path . ' devrait etre interdit a un joueur');
        }
    }

    public function testEveryAdminEndpointRejectsAClub(): void
    {
        $token = $this->registerAndLogin('club-vs-admin', 'ROLE_CLUB');

        foreach (self::ENDPOINTS as [$method, $path]) {
            $this->client->request($method, $path, [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ]);
            $this->assertResponseStatusCodeSame(403, $path . ' devrait etre interdit a un club');
        }
    }

    public function testAdminCanReachTheAdminArea(): void
    {
        $this->get('/api/admin/stats', $this->adminToken());

        $this->assertResponseIsSuccessful();
    }

    // ─── Statistiques ───────────────────────────────────────────

    public function testStatsReturnsCounters(): void
    {
        $this->get('/api/admin/stats', $this->adminToken());

        $this->assertResponseIsSuccessful();

        $data = $this->getJsonResponse();
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('byRole', $data['users']);
        $this->assertArrayHasKey('ROLE_PLAYER', $data['users']['byRole']);
        $this->assertGreaterThan(0, $data['users']['total']);
    }

    // ─── Utilisateurs ───────────────────────────────────────────

    public function testUserListingNeverExposesPasswords(): void
    {
        $this->get('/api/admin/users', $this->adminToken());

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsStringIgnoringCase(
            'password',
            (string) $this->client->getResponse()->getContent()
        );
    }

    public function testUserListingCanBeFilteredByRole(): void
    {
        $this->registerAndLogin('filter-club', 'ROLE_CLUB');

        $this->get('/api/admin/users?role=ROLE_CLUB', $this->adminToken());

        $this->assertResponseIsSuccessful();

        $roles = array_column($this->getJsonResponse(), 'role');
        $this->assertNotEmpty($roles);
        $this->assertSame(['ROLE_CLUB'], array_values(array_unique($roles)));
    }

    public function testUserListingRejectsAnUnknownRole(): void
    {
        $this->get('/api/admin/users?role=ROLE_NOPE', $this->adminToken());

        $this->assertResponseStatusCodeSame(400);
    }

    // ─── Activation / désactivation ─────────────────────────────

    public function testDeactivatingAUserPreventsThemFromLoggingIn(): void
    {
        $email = $this->uniqueEmail('to-deactivate');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_PLAYER',
        ]);
        $this->assertResponseStatusCodeSame(201);
        $userId = $this->getJsonResponse()['id'];

        // La connexion fonctionne avant desactivation.
        $this->postJson('/api/login_check', ['username' => $email, 'password' => 'secretpass123']);
        $this->assertResponseIsSuccessful();

        $this->patch('/api/admin/users/' . $userId . '/status', ['isActive' => false], $this->adminToken());
        $this->assertResponseIsSuccessful();
        $this->assertFalse($this->getJsonResponse()['isActive']);

        // Et ne fonctionne plus apres.
        $this->postJson('/api/login_check', ['username' => $email, 'password' => 'secretpass123']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testReactivatingAUserRestoresAccess(): void
    {
        $email = $this->uniqueEmail('to-reactivate');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_PLAYER',
        ]);
        $userId = $this->getJsonResponse()['id'];

        $token = $this->adminToken();
        $this->patch('/api/admin/users/' . $userId . '/status', ['isActive' => false], $token);
        $this->patch('/api/admin/users/' . $userId . '/status', ['isActive' => true], $token);
        $this->assertResponseIsSuccessful();

        $this->postJson('/api/login_check', ['username' => $email, 'password' => 'secretpass123']);
        $this->assertResponseIsSuccessful();
    }

    public function testAnAdminCannotDeactivateTheirOwnAccount(): void
    {
        $email = $this->uniqueEmail('self-lockout');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_ADMIN',
        ]);
        $userId = $this->getJsonResponse()['id'];

        $this->postJson('/api/login_check', ['username' => $email, 'password' => 'secretpass123']);
        $token = $this->getJsonResponse()['token'];

        $this->patch('/api/admin/users/' . $userId . '/status', ['isActive' => false], $token);

        $this->assertResponseStatusCodeSame(409);
    }

    public function testStatusUpdateRequiresABooleanFlag(): void
    {
        $token = $this->adminToken();
        $this->get('/api/admin/users', $token);
        $userId = $this->getJsonResponse()[0]['id'];

        $this->patch('/api/admin/users/' . $userId . '/status', [], $token);
        $this->assertResponseStatusCodeSame(400);

        $this->patch('/api/admin/users/' . $userId . '/status', ['isActive' => 'yes'], $token);
        $this->assertResponseStatusCodeSame(400);
    }

    // ─── Vérification des clubs ─────────────────────────────────

    public function testANewClubStartsUnverified(): void
    {
        $token = $this->registerAndLogin('fresh-club', 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'Fresh Club ' . bin2hex(random_bytes(3))], $token);
        $this->assertResponseStatusCodeSame(201);

        $this->get('/api/admin/clubs?verified=false', $this->adminToken());

        $this->assertResponseIsSuccessful();
        $this->assertNotEmpty($this->getJsonResponse());
    }

    public function testAdminCanVerifyAndUnverifyAClub(): void
    {
        $clubToken = $this->registerAndLogin('verify-me', 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'Verify Me ' . bin2hex(random_bytes(3))], $clubToken);
        $clubId = $this->getJsonResponse()['id'];

        $adminToken = $this->adminToken();

        $this->patch('/api/admin/clubs/' . $clubId . '/verify', ['isVerified' => true], $adminToken);
        $this->assertResponseIsSuccessful();
        $this->assertTrue($this->getJsonResponse()['isVerified']);

        $this->patch('/api/admin/clubs/' . $clubId . '/verify', ['isVerified' => false], $adminToken);
        $this->assertResponseIsSuccessful();
        $this->assertFalse($this->getJsonResponse()['isVerified']);
    }

    public function testClubVerificationRequiresABooleanFlag(): void
    {
        $clubToken = $this->registerAndLogin('bad-verify', 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'Bad Verify ' . bin2hex(random_bytes(3))], $clubToken);
        $clubId = $this->getJsonResponse()['id'];

        $this->patch('/api/admin/clubs/' . $clubId . '/verify', ['isVerified' => 'oui'], $this->adminToken());

        $this->assertResponseStatusCodeSame(400);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function adminToken(): string
    {
        return $this->registerAndLogin('admin-' . bin2hex(random_bytes(3)), 'ROLE_ADMIN');
    }

    private function get(string $path, string $token): void
    {
        $this->client->request('GET', $path, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function patch(string $path, array $payload, string $token): void
    {
        $this->client->request(
            'PATCH',
            $path,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            ],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }
}
