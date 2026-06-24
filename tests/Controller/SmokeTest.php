<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test fumigene : verifie que le kernel Symfony demarre et repond.
 * Si ce test passe, le setup PHPUnit est OK.
 */
class SmokeTest extends WebTestCase
{
    public function testHomepageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/players');

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testUnknownRouteReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/this-does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }
}