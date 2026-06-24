<?php

declare(strict_types=1);

namespace App\Tests\Controller;

class ApplicationControllerTest extends ApiTestCase
{
    // ─── Listings ───────────────────────────────────────────────

    public function testListMineWithoutTokenReturns401(): void
    {
        $this->getJson('/api/applications/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListMineAsClubReturns403(): void
    {
        $token = $this->registerAndLogin('list-mine-club', 'ROLE_CLUB');
        $this->getJson('/api/applications/me', $token);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testListMineAsPlayerWorks(): void
    {
        $token = $this->createPlayer('list-mine');
        $this->getJson('/api/applications/me', $token);
        $this->assertResponseStatusCodeSame(200);
        $this->assertIsArray($this->getJsonResponse());
    }

    public function testListForClubWithoutTokenReturns401(): void
    {
        $this->getJson('/api/clubs/me/applications');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testListForClubAsPlayerReturns403(): void
    {
        $token = $this->createPlayer('list-club-player');
        $this->getJson('/api/clubs/me/applications', $token);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testListForClubAsClubWorks(): void
    {
        $token = $this->createClub('list-club');
        $this->getJson('/api/clubs/me/applications', $token);
        $this->assertResponseStatusCodeSame(200);
    }

    // ─── Apply (POST) ───────────────────────────────────────────

    public function testApplyAsClubReturns403(): void
    {
        $token = $this->createClub('apply-as-club');

        $this->postJson('/api/applications', ['offerId' => 1, 'message' => 'hi'], $token);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testApplyWithoutTokenReturns401(): void
    {
        $this->postJson('/api/applications', ['offerId' => 1, 'message' => 'hi']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testApplyToUnknownOfferReturns404(): void
    {
        $token = $this->createPlayer('apply-404');

        $this->postJson('/api/applications', ['offerId' => 999999, 'message' => 'test'], $token);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testApplyWithMissingOfferIdReturns400(): void
    {
        $token = $this->createPlayer('apply-noid');

        $this->postJson('/api/applications', ['message' => 'test'], $token);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testApplySuccessfullyReturns201(): void
    {
        $offerId = $this->createOfferAsAClub('apply-offer-creator');
        $tokenPlayer = $this->createPlayer('apply-success');

        $this->postJson('/api/applications', [
            'offerId' => $offerId,
            'message' => 'Bonjour, je suis tres motive pour rejoindre votre equipe.',
        ], $tokenPlayer);

        $this->assertResponseStatusCodeSame(201);
    }

    public function testDoubleApplicationReturns409(): void
    {
        $offerId = $this->createOfferAsAClub('double-club');
        $tokenPlayer = $this->createPlayer('double-applicant');

        $payload = [
            'offerId' => $offerId,
            'message' => 'Premiere tentative de candidature au poste.',
        ];

        // 1ere candidature OK
        $this->postJson('/api/applications', $payload, $tokenPlayer);
        $this->assertResponseStatusCodeSame(201);

        // 2e candidature -> 409
        $this->postJson('/api/applications', $payload, $tokenPlayer);
        $this->assertResponseStatusCodeSame(409);
    }

    // ─── Update status ──────────────────────────────────────────

    public function testUpdateStatusByOwningClubWorks(): void
    {
        // Le club cree une offre
        $tokenClub = $this->registerAndLogin('upd-status-club', 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'StatusClub'], $tokenClub);
        $this->postJson('/api/offers', $this->validOfferPayload(), $tokenClub);
        $offerId = $this->getJsonResponse()['id'];

        // Un joueur postule
        $tokenPlayer = $this->createPlayer('upd-status-applicant');
        $this->postJson('/api/applications', [
            'offerId' => $offerId,
            'message' => 'Je postule a cette offre, merci de me considerer.',
        ], $tokenPlayer);
        $applicationId = $this->getJsonResponse()['id'];

        // Le club accepte
        $this->patchJson("/api/applications/{$applicationId}", ['status' => 'ACCEPTEE'], $tokenClub);
        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('ACCEPTEE', $this->getJsonResponse()['status']);
    }

    public function testUpdateStatusByOtherClubReturns403(): void
    {
        // Club A cree une offre + joueur postule
        $tokenClubA = $this->registerAndLogin('upd-club-a', 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'ClubA'], $tokenClubA);
        $this->postJson('/api/offers', $this->validOfferPayload(), $tokenClubA);
        $offerId = $this->getJsonResponse()['id'];

        $tokenPlayer = $this->createPlayer('upd-applicant');
        $this->postJson('/api/applications', [
            'offerId' => $offerId,
            'message' => 'Test application.',
        ], $tokenPlayer);
        $applicationId = $this->getJsonResponse()['id'];

        // Club B essaie de changer le statut -> 403
        $tokenClubB = $this->createClub('upd-club-b');
        $this->patchJson("/api/applications/{$applicationId}", ['status' => 'ACCEPTEE'], $tokenClubB);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testUpdateStatusWithInvalidValueReturns400(): void
    {
        $tokenClub = $this->registerAndLogin('upd-inv-club', 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'InvClub'], $tokenClub);
        $this->postJson('/api/offers', $this->validOfferPayload(), $tokenClub);
        $offerId = $this->getJsonResponse()['id'];

        $tokenPlayer = $this->createPlayer('upd-inv-applicant');
        $this->postJson('/api/applications', [
            'offerId' => $offerId,
            'message' => 'Test application content.',
        ], $tokenPlayer);
        $applicationId = $this->getJsonResponse()['id'];

        $this->patchJson("/api/applications/{$applicationId}", ['status' => 'INVALID_STATUS'], $tokenClub);
        $this->assertResponseStatusCodeSame(400);
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function validOfferPayload(): array
    {
        return [
            'title' => 'Offre de test',
            'description' => 'Description de test pour un poste qui devrait passer la validation.',
            'wantedRole' => 'MID',
            'minimumRank' => 'Gold IV',
        ];
    }

    private function validPlayerPayload(string $pseudo): array
    {
        return [
            'pseudo' => $pseudo,
            'firstName' => 'Test',
            'lastName' => 'Player',
            'gameRole' => 'MID',
            'isAvailable' => true,
        ];
    }

    private function createPlayer(string $emailPrefix): string
    {
        $token = $this->registerAndLogin($emailPrefix, 'ROLE_PLAYER');
        $this->postJson('/api/players', $this->validPlayerPayload('Player' . bin2hex(random_bytes(2))), $token);

        return $token;
    }

    private function createClub(string $emailPrefix): string
    {
        $token = $this->registerAndLogin($emailPrefix, 'ROLE_CLUB');
        $this->postJson('/api/clubs', ['name' => 'Club' . bin2hex(random_bytes(2))], $token);

        return $token;
    }

    /**
     * Cree un club + une offre, retourne l'offerId.
     */
    private function createOfferAsAClub(string $emailPrefix): int
    {
        $token = $this->createClub($emailPrefix);
        $this->postJson('/api/offers', $this->validOfferPayload(), $token);

        return $this->getJsonResponse()['id'];
    }
}