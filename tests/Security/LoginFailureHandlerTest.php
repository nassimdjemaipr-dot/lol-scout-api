<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\LoginFailureHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

class LoginFailureHandlerTest extends TestCase
{
    private function handle(\Throwable $exception): Response
    {
        return (new LoginFailureHandler())
            ->onAuthenticationFailure(new Request(), $exception);
    }

    public function testBadCredentialsStillReturns401(): void
    {
        $response = $this->handle(new BadCredentialsException());

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertFalse($response->headers->has('Retry-After'));
    }

    public function testThrottlingReturns429(): void
    {
        $response = $this->handle(new TooManyLoginAttemptsAuthenticationException(1));

        $this->assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    public function testThrottlingCarriesRetryAfterInSeconds(): void
    {
        $response = $this->handle(new TooManyLoginAttemptsAuthenticationException(1));

        $this->assertSame('60', $response->headers->get('Retry-After'));
    }

    public function testRetryAfterFollowsTheConfiguredThreshold(): void
    {
        $response = $this->handle(new TooManyLoginAttemptsAuthenticationException(3));

        $this->assertSame('180', $response->headers->get('Retry-After'));
    }

    public function testThrottlingMessageIsInFrenchAndActionable(): void
    {
        $response = $this->handle(new TooManyLoginAttemptsAuthenticationException(1));

        $payload = json_decode((string) $response->getContent(), true);

        $this->assertSame(429, $payload['code']);
        $this->assertStringContainsString('Trop de tentatives', $payload['message']);
        $this->assertStringContainsString('1 minute', $payload['message']);
    }
}
