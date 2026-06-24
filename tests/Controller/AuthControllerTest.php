<?php

declare(strict_types=1);

namespace App\Tests\Controller;

class AuthControllerTest extends ApiTestCase
{
    // ─── REGISTER ───────────────────────────────────────────────

    public function testRegisterSuccess(): void
    {
        $email = $this->uniqueEmail('newuser');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_PLAYER',
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $this->getJsonResponse();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame($email, $data['email']);
        $this->assertSame('ROLE_PLAYER', $data['role']);
    }

    public function testRegisterFailsWithMissingFields(): void
    {
        $this->postJson('/api/register', [
            'email' => $this->uniqueEmail('incomplete'),
            // password manquant
            'role' => 'ROLE_PLAYER',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRegisterFailsWithInvalidRole(): void
    {
        $this->postJson('/api/register', [
            'email' => $this->uniqueEmail('badrole'),
            'password' => 'secretpass123',
            'role' => 'ROLE_NOPE',
        ]);

        $this->assertResponseStatusCodeSame(400);
        $data = $this->getJsonResponse();
        $this->assertSame('Invalid role', $data['error']);
    }

    public function testRegisterFailsIfEmailAlreadyUsed(): void
    {
        $email = $this->uniqueEmail('dup');

        // 1er enregistrement OK
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_PLAYER',
        ]);
        $this->assertResponseStatusCodeSame(201);

        // 2e tentative avec le meme email -> 409
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'anotherpass456',
            'role' => 'ROLE_CLUB',
        ]);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testRegisterFailsWithInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/api/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'this-is-not-json'
        );

        $this->assertResponseStatusCodeSame(400);
    }

    // ─── LOGIN ──────────────────────────────────────────────────

    public function testLoginSuccessReturnsJwtToken(): void
    {
        $token = $this->registerAndLogin('login', 'ROLE_PLAYER');
        $this->assertNotEmpty($token);
    }

    public function testLoginFailsWithBadPassword(): void
    {
        $email = $this->uniqueEmail('badpass');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'correctpass',
            'role' => 'ROLE_PLAYER',
        ]);

        $this->postJson('/api/login_check', [
            'username' => $email,
            'password' => 'WRONG-PASSWORD',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginFailsWithUnknownEmail(): void
    {
        $this->postJson('/api/login_check', [
            'username' => 'ghost-' . bin2hex(random_bytes(4)) . '@nowhere.local',
            'password' => 'whatever',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ─── /api/me ────────────────────────────────────────────────

    public function testMeWithoutTokenReturns401(): void
    {
        $this->getJson('/api/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testMeWithValidTokenReturnsUserInfo(): void
    {
        $token = $this->registerAndLogin('me', 'ROLE_CLUB');
        $this->getJson('/api/me', $token);

        $this->assertResponseStatusCodeSame(200);
        $data = $this->getJsonResponse();
        $this->assertSame('ROLE_CLUB', $data['role']);
    }
}