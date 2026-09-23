<?php

declare(strict_types=1);

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationFailureResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * LexikJWT traduit toute exception d'authentification en 401. Un blocage pour
 * trop de tentatives n'est pourtant pas un probleme d'identifiants : le client
 * doit pouvoir distinguer « mot de passe errone », ou il faut ressaisir, de
 * « limite atteinte », ou il faut attendre.
 */
class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if (!$exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JWTAuthenticationFailureResponse($exception->getMessageKey());
        }

        $minutes = (int) ($exception->getMessageData()['%minutes%'] ?? 1);
        $seconds = max(1, $minutes) * 60;

        $response = new JWTAuthenticationFailureResponse(
            sprintf(
                'Trop de tentatives de connexion. Réessayez dans %d minute%s.',
                max(1, $minutes),
                $minutes > 1 ? 's' : ''
            ),
            Response::HTTP_TOO_MANY_REQUESTS
        );

        $response->headers->set('Retry-After', (string) $seconds);

        return $response;
    }
}
