<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $roleValue = $data['role'] ?? null;

        if (!$email || !$password || !$roleValue) {
            return $this->json(
                ['error' => 'Missing fields: email, password and role are required'],
                400
            );
        }

        $role = UserRole::tryFrom($roleValue);
        if ($role === null) {
            return $this->json(
                [
                    'error' => 'Invalid role',
                    'allowed' => array_map(fn (UserRole $r) => $r->value, UserRole::cases()),
                ],
                400
            );
        }

        $existing = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing !== null) {
            return $this->json(['error' => 'Email already used'], 409);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRole($role);
        $user->setPassword($hasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        return $this->json(
            [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()?->value,
            ],
            201
        );
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole()?->value,
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}
