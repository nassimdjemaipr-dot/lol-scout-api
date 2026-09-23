<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;

class AccountDeletionTest extends ApiTestCase
{
    public function testDeleteMeRequiresAuthentication(): void
    {
        $this->client->request('DELETE', '/api/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteMeAnonymizesTheAccount(): void
    {
        $email = $this->uniqueEmail('todelete');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_PLAYER',
        ]);
        $this->assertResponseStatusCodeSame(201);

        $this->postJson('/api/login_check', [
            'username' => $email,
            'password' => 'secretpass123',
        ]);
        $token = $this->getJsonResponse()['token'];

        $this->client->request(
            'DELETE',
            '/api/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseStatusCodeSame(204);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();

        $this->assertNull(
            $em->getRepository(User::class)->findOneBy(['email' => $email]),
            "L'adresse d'origine ne doit plus exister en base"
        );
    }

    public function testDeletedAccountCanNoLongerLogIn(): void
    {
        $email = $this->uniqueEmail('nologin');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_PLAYER',
        ]);

        $this->postJson('/api/login_check', [
            'username' => $email,
            'password' => 'secretpass123',
        ]);
        $token = $this->getJsonResponse()['token'];

        $this->client->request(
            'DELETE',
            '/api/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseStatusCodeSame(204);

        $this->postJson('/api/login_check', [
            'username' => $email,
            'password' => 'secretpass123',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testJwtIssuedBeforeDeletionStopsWorking(): void
    {
        $email = $this->uniqueEmail('staletoken');
        $this->postJson('/api/register', [
            'email' => $email,
            'password' => 'secretpass123',
            'role' => 'ROLE_PLAYER',
        ]);

        $this->postJson('/api/login_check', [
            'username' => $email,
            'password' => 'secretpass123',
        ]);
        $token = $this->getJsonResponse()['token'];

        $this->client->request(
            'DELETE',
            '/api/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );
        $this->assertResponseStatusCodeSame(204);

        // Le jeton n'a pas expiré, mais le compte est désactivé :
        // le user_checker doit le refuser au chargement de l'utilisateur.
        $this->client->request(
            'GET',
            '/api/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCodeSame(401);
    }
}
