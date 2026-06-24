<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Classe de base pour les tests fonctionnels d'API.
 * Mutualise les helpers communs : creation de client, helpers JSON,
 * inscription + login pour recuperer un JWT.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /**
     * POST avec un corps JSON.
     */
    protected function postJson(string $url, array $payload, ?string $token = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        $this->client->request(
            'POST',
            $url,
            [],
            [],
            $headers,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * PATCH avec un corps JSON.
     */
    protected function patchJson(string $url, array $payload, ?string $token = null): void
    {
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        $this->client->request(
            'PATCH',
            $url,
            [],
            [],
            $headers,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * GET avec ou sans token Bearer.
     */
    protected function getJson(string $url, ?string $token = null): void
    {
        $headers = [];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        $this->client->request('GET', $url, [], [], $headers);
    }

    /**
     * DELETE avec token.
     */
    protected function deleteJson(string $url, ?string $token = null): void
    {
        $headers = [];
        if ($token !== null) {
            $headers['HTTP_AUTHORIZATION'] = "Bearer {$token}";
        }

        $this->client->request('DELETE', $url, [], [], $headers);
    }

    /**
     * Decode la derniere reponse JSON en tableau associatif.
     *
     * @return array<string, mixed>|array<int, mixed>
     */
    protected function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        return json_decode($content !== false ? $content : '{}', true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Enregistre un utilisateur avec un email unique (suffixe random) et retourne son JWT.
     * L'email final est : prefixe + suffixe random + @test.local
     * Le prefixe est utile pour identifier le test dans les logs.
     */
    protected function registerAndLogin(string $emailPrefix, string $role = 'ROLE_PLAYER', string $password = 'password'): string
    {
        $email = $this->uniqueEmail($emailPrefix);

        $this->postJson('/api/register', [
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ]);
        $registerStatus = $this->client->getResponse()->getStatusCode();
        if ($registerStatus !== 201) {
            $this->fail("Register failed (status {$registerStatus}): " . $this->client->getResponse()->getContent());
        }

        $this->postJson('/api/login_check', [
            'username' => $email,
            'password' => $password,
        ]);
        $loginStatus = $this->client->getResponse()->getStatusCode();
        if ($loginStatus !== 200) {
            $this->fail("Login failed (status {$loginStatus}): " . $this->client->getResponse()->getContent());
        }

        $data = $this->getJsonResponse();
        if (!isset($data['token'])) {
            $this->fail('No token in login response: ' . $this->client->getResponse()->getContent());
        }

        return $data['token'];
    }

    /**
     * Genere un email unique pour eviter les collisions entre tests.
     * Format : <prefix>-<random>@test.local
     */
    protected function uniqueEmail(string $prefix): string
    {
        // bin2hex(random_bytes(6)) = 12 chars hex, hautement unique
        return $prefix . '-' . bin2hex(random_bytes(6)) . '@test.local';
    }
}