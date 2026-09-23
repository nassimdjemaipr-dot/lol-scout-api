<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\AccountAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        $roleValue = $data['role'] ?? null;

        if (!is_string($email) || $email === ''
            || !is_string($password) || $password === ''
            || !is_string($roleValue) || $roleValue === ''
        ) {
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
        $user->setPlainPassword($password);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $user->setPassword($hasher->hashPassword($user, $password));
        $user->eraseCredentials();

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

    #[Route('/me', name: 'api_delete_me', methods: ['DELETE'])]
    public function deleteMe(AccountAnonymizer $anonymizer): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $anonymizer->anonymize($user);

        return $this->json(null, 204);
    }

    /**
     * @param iterable<\Symfony\Component\Validator\ConstraintViolationInterface> $errors
     * @return array<array{field: string, message: string}>
     */
    private function formatErrors(iterable $errors): array
    {
        $result = [];
        foreach ($errors as $error) {
            $result[] = [
                'field' => $error->getPropertyPath(),
                'message' => $error->getMessage(),
            ];
        }

        return $result;
    }
}
