<?php

declare(strict_types=1);

namespace App\Tests\Controller;

class PlayerControllerTest extends ApiTestCase
{
    // ─── Listing public ─────────────────────────────────────────

    public function testListIsPubliclyAccessible(): void
    {
        $this->getJson('/api/players');

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = $this->getJsonResponse();
        $this->assertIsArray($data);
    }

    public function testListWithRoleFilterReturnsOnlyMatchingPlayers(): void
    {
        // On cree un joueur ADC + un MID
        $this->createPlayer('adc-test@test.com', 'ADCPlayer', 'ADC');
        $this->createPlayer('mid-test@test.com', 'MIDPlayer', 'MID');

        // Filtre par ADC
        $this->getJson('/api/players?role=ADC');
        $this->assertResponseStatusCodeSame(200);

        $players = $this->getJsonResponse();
        foreach ($players as $player) {
            $this->assertSame('ADC', $player['gameRole']);
        }
    }

    public function testListWithInvalidRoleReturns400(): void
    {
        $this->getJson('/api/players?role=NOTAROLE');

        $this->assertResponseStatusCodeSame(400);
        $data = $this->getJsonResponse();
        $this->assertSame('Invalid role', $data['error']);
    }

    public function testListWithAvailableFilterWorks(): void
    {
        $this->getJson('/api/players?available=true');
        $this->assertResponseStatusCodeSame(200);
    }

    // ─── /api/players/me ────────────────────────────────────────

    public function testMeWithoutTokenReturns401(): void
    {
        $this->getJson('/api/players/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeReturns404IfNoPlayerProfileYet(): void
    {
        $token = $this->registerAndLogin('noprofile@test.com', 'ROLE_PLAYER');

        $this->getJson('/api/players/me', $token);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMeReturnsTheConnectedPlayer(): void
    {
        $token = $this->createPlayer('me@test.com', 'MyPseudo', 'TOP');

        $this->getJson('/api/players/me', $token);
        $this->assertResponseStatusCodeSame(200);

        $data = $this->getJsonResponse();
        $this->assertSame('MyPseudo', $data['pseudo']);
        $this->assertSame('TOP', $data['gameRole']);
    }

    // ─── Creation (POST) ────────────────────────────────────────

    public function testCreatePlayerSuccess(): void
    {
        $token = $this->registerAndLogin('create@test.com', 'ROLE_PLAYER');

        $this->postJson('/api/players', $this->validPlayerPayload('NewPseudo', 'JUNGLE'), $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getJsonResponse();
        $this->assertSame('NewPseudo', $data['pseudo']);
    }

    public function testCreatePlayerFailsIfAlreadyHasProfile(): void
    {
        $token = $this->createPlayer('dup@test.com', 'FirstPseudo', 'SUPPORT');

        // 2e tentative -> 409
        $this->postJson('/api/players', $this->validPlayerPayload('SecondPseudo', 'MID'), $token);

        $this->assertResponseStatusCodeSame(409);
    }

    public function testCreatePlayerWithoutTokenReturns401(): void
    {
        $this->postJson('/api/players', $this->validPlayerPayload('NoAuth', 'TOP'));

        $this->assertResponseStatusCodeSame(401);
    }

    // ─── Detail (GET /api/players/{id}) ─────────────────────────

    public function testGetPlayerByIdReturnsPlayer(): void
    {
        $this->createPlayer('detail@test.com', 'DetailPlayer', 'ADC');
        $this->getJson('/api/players');
        $players = $this->getJsonResponse();

        // On prend le premier joueur retourne
        $id = $players[0]['id'];

        $this->getJson('/api/players/' . $id);
        $this->assertResponseStatusCodeSame(200);

        $data = $this->getJsonResponse();
        $this->assertSame($id, $data['id']);
    }

    public function testGetUnknownPlayerReturns404(): void
    {
        $this->getJson('/api/players/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    // ─── Update (PATCH /api/players/{id}) ───────────────────────

    public function testUpdateOwnProfileWorks(): void
    {
        $token = $this->createPlayer('update@test.com', 'BeforeName', 'MID');

        $this->getJson('/api/players/me', $token);
        $playerId = $this->getJsonResponse()['id'];

        $this->patchJson("/api/players/{$playerId}", [
            'pseudo' => 'AfterName',
        ], $token);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('AfterName', $this->getJsonResponse()['pseudo']);
    }

    public function testUpdateOtherPlayerReturns403(): void
    {
        // joueur A cree un profil
        $tokenA = $this->createPlayer('a@test.com', 'PlayerA', 'TOP');
        $this->getJson('/api/players/me', $tokenA);
        $playerAId = $this->getJsonResponse()['id'];

        // joueur B essaie de modifier A
        $tokenB = $this->createPlayer('b@test.com', 'PlayerB', 'JUNGLE');

        $this->patchJson("/api/players/{$playerAId}", [
            'pseudo' => 'Hacked',
        ], $tokenB);

        $this->assertResponseStatusCodeSame(403);
    }

    // ─── Riot (link without payload) ────────────────────────────

    public function testLinkRiotAccountWithoutPayloadReturns400(): void
    {
        $token = $this->createPlayer('riot@test.com', 'RiotPlayer', 'ADC');

        $this->postJson('/api/players/me/riot-account', [], $token);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSyncRiotWithoutLinkedAccountReturns404(): void
    {
        $token = $this->createPlayer('sync@test.com', 'SyncPlayer', 'SUPPORT');

        $this->postJson('/api/players/me/sync-riot', [], $token);
        $this->assertResponseStatusCodeSame(404);
    }

    // ─── Helpers prives ─────────────────────────────────────────

    /**
     * Payload valide qui passe la validation Symfony (firstName/lastName requis).
     */
    private function validPlayerPayload(string $pseudo, string $role): array
    {
        return [
            'pseudo' => $pseudo,
            'firstName' => 'Test',
            'lastName' => 'Player',
            'gameRole' => $role,
            'isAvailable' => true,
        ];
    }

    /**
     * Cree un user + son profil joueur, retourne le JWT.
     */
    private function createPlayer(string $email, string $pseudo, string $role): string
    {
        $token = $this->registerAndLogin($email, 'ROLE_PLAYER');
        $this->postJson('/api/players', $this->validPlayerPayload($pseudo, $role), $token);

        return $token;
    }
}