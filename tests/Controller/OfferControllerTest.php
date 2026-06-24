<?php

declare(strict_types=1);

namespace App\Tests\Controller;

class OfferControllerTest extends ApiTestCase
{
    // ─── Listing public ─────────────────────────────────────────

    public function testListIsPubliclyAccessible(): void
    {
        $this->getJson('/api/offers');
        $this->assertResponseStatusCodeSame(200);
    }

    public function testListWithInvalidRoleReturns400(): void
    {
        $this->getJson('/api/offers?role=INVALID');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetUnknownOfferReturns404(): void
    {
        $this->getJson('/api/offers/999999');
        $this->assertResponseStatusCodeSame(404);
    }

    // ─── Creation ───────────────────────────────────────────────

    public function testCreateOfferAsClubWorks(): void
    {
        $token = $this->createClub('offer-creator');

        $this->postJson('/api/offers', $this->validOfferPayload(), $token);

        $this->assertResponseStatusCodeSame(201);
        $data = $this->getJsonResponse();
        $this->assertSame('Recherche MID Diamond+', $data['title']);
    }

    public function testCreateOfferAsPlayerReturns403(): void
    {
        // Un joueur (pas un club) essaie de creer une offre
        $token = $this->registerAndLogin('player-creator', 'ROLE_PLAYER');

        $this->postJson('/api/offers', $this->validOfferPayload(), $token);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateOfferWithoutTokenReturns401(): void
    {
        $this->postJson('/api/offers', $this->validOfferPayload());
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateOfferWithoutTitleReturns422(): void
    {
        $token = $this->createClub('notitle');

        $payload = $this->validOfferPayload();
        unset($payload['title']);
        $this->postJson('/api/offers', $payload, $token);

        $this->assertResponseStatusCodeSame(422);
    }

    // ─── Update / Delete ─────────────────────────────────────────

    public function testUpdateOfferFromOtherClubReturns403(): void
    {
        // Club A cree une offre
        $tokenA = $this->createClub('upd-a');
        $this->postJson('/api/offers', $this->validOfferPayload(), $tokenA);
        $offerId = $this->getJsonResponse()['id'];

        // Club B essaie de la modifier
        $tokenB = $this->createClub('upd-b');
        $this->patchJson("/api/offers/{$offerId}", ['title' => 'Hacked'], $tokenB);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteOwnOfferReturns204(): void
    {
        $token = $this->createClub('del-club');
        $this->postJson('/api/offers', $this->validOfferPayload(), $token);
        $offerId = $this->getJsonResponse()['id'];

        $this->deleteJson("/api/offers/{$offerId}", $token);
        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteOtherClubOfferReturns403(): void
    {
        $tokenA = $this->createClub('del-a');
        $this->postJson('/api/offers', $this->validOfferPayload(), $tokenA);
        $offerId = $this->getJsonResponse()['id'];

        $tokenB = $this->createClub('del-b');
        $this->deleteJson("/api/offers/{$offerId}", $tokenB);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateOwnOfferWorks(): void
    {
        $token = $this->createClub('own-upd');
        $this->postJson('/api/offers', $this->validOfferPayload(), $token);
        $offerId = $this->getJsonResponse()['id'];

        $this->patchJson("/api/offers/{$offerId}", ['title' => 'Updated Title Here'], $token);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('Updated Title Here', $this->getJsonResponse()['title']);
    }

    // ─── Helpers prives ─────────────────────────────────────────

    private function validOfferPayload(): array
    {
        return [
            'title' => 'Recherche MID Diamond+',
            'description' => 'Equipe ambitieuse cherche midlaner Diamond+ pour la saison 2026.',
            'wantedRole' => 'MID',
            'minimumRank' => 'Diamond IV',
        ];
    }

    private function createClub(string $emailPrefix): string
    {
        $token = $this->registerAndLogin($emailPrefix, 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'Club-' . bin2hex(random_bytes(3))], $token);

        return $token;
    }
}