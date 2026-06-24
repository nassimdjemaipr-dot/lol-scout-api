<?php

declare(strict_types=1);

namespace App\Tests\Controller;

class ClubControllerTest extends ApiTestCase
{
    public function testListIsPubliclyAccessible(): void
    {
        $this->getJson('/api/clubs');
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testGetUnknownClubReturns404(): void
    {
        $this->getJson('/api/clubs/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMeWithoutTokenReturns401(): void
    {
        $this->getJson('/api/clubs/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeReturns404IfNoClubProfile(): void
    {
        $token = $this->registerAndLogin('noclub', 'ROLE_CLUB');
        $this->getJson('/api/clubs/me', $token);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateClubSuccess(): void
    {
        $token = $this->registerAndLogin('create-club', 'ROLE_CLUB');

        $this->postJson('/api/clubs', [
            'name' => 'My Test Club',
            'description' => 'A great club for testing.',
        ], $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getJsonResponse();
        $this->assertSame('My Test Club', $data['name']);
    }

    public function testCreateClubFailsIfAlreadyHasOne(): void
    {
        $token = $this->createClub('dup-club', 'First Club');

        $this->postJson('/api/clubs', ['name' => 'Second Club'], $token);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testCreateClubWithoutTokenReturns401(): void
    {
        $this->postJson('/api/clubs', ['name' => 'NoAuth Club']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateClubWithoutNameReturns422(): void
    {
        $token = $this->registerAndLogin('noname-club', 'ROLE_CLUB');

        $this->postJson('/api/clubs', ['description' => 'No name'], $token);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testMeReturnsTheConnectedClub(): void
    {
        $token = $this->createClub('me-club', 'MyClub');
        $this->getJson('/api/clubs/me', $token);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('MyClub', $this->getJsonResponse()['name']);
    }

    public function testUpdateOwnClubWorks(): void
    {
        $token = $this->createClub('upd-club', 'BeforeName');

        $this->getJson('/api/clubs/me', $token);
        $clubId = $this->getJsonResponse()['id'];

        $this->patchJson("/api/clubs/{$clubId}", ['name' => 'AfterName'], $token);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('AfterName', $this->getJsonResponse()['name']);
    }

    public function testUpdateOtherClubReturns403(): void
    {
        $tokenA = $this->createClub('club-a', 'ClubA');
        $this->getJson('/api/clubs/me', $tokenA);
        $clubAId = $this->getJsonResponse()['id'];

        $tokenB = $this->createClub('club-b', 'ClubB');

        $this->patchJson("/api/clubs/{$clubAId}", ['name' => 'Hacked'], $tokenB);
        $this->assertResponseStatusCodeSame(403);
    }

    // ─── Helper ─────────────────────────────────────────────────

    private function createClub(string $emailPrefix, string $name): string
    {
        $token = $this->registerAndLogin($emailPrefix, 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => $name], $token);

        return $token;
    }
}