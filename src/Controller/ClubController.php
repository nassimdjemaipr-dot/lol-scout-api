<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/clubs')]
class ClubController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClubRepository $clubRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_club_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $clubs = $this->clubRepository->findAll();

        return $this->json(
            $clubs,
            200,
            [],
            ['groups' => ['club:read']]
        );
    }

    #[Route('/me', name: 'api_club_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $club = $this->clubRepository->findOneBy(['user' => $user]);

        if ($club === null) {
            return $this->json(['error' => 'No club profile for this user'], 404);
        }

        return $this->json($club, 200, [], ['groups' => ['club:read']]);
    }

    #[Route('/{id}', name: 'api_club_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(Club $club): JsonResponse
    {
        return $this->json($club, 200, [], ['groups' => ['club:read']]);
    }

    #[Route('', name: 'api_club_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $existing = $this->clubRepository->findOneBy(['user' => $user]);
        if ($existing !== null) {
            return $this->json(['error' => 'User already has a club profile'], 409);
        }

        try {
            $club = $this->serializer->deserialize(
                $request->getContent(),
                Club::class,
                'json',
                ['groups' => ['club:write']]
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid JSON: '.$e->getMessage()], 400);
        }

        $club->setUser($user);

        $errors = $this->validator->validate($club);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $this->em->persist($club);
        $this->em->flush();

        return $this->json($club, 201, [], ['groups' => ['club:read']]);
    }

    #[Route('/{id}', name: 'api_club_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(Club $club, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($club->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You can only edit your own club'], 403);
        }

        try {
            $this->serializer->deserialize(
                $request->getContent(),
                Club::class,
                'json',
                [
                    'groups' => ['club:write'],
                    'object_to_populate' => $club,
                ]
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid JSON: '.$e->getMessage()], 400);
        }

        $errors = $this->validator->validate($club);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $this->em->flush();

        return $this->json($club, 200, [], ['groups' => ['club:read']]);
    }

    #[Route('/{id}', name: 'api_club_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Club $club): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($club->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You can only delete your own club'], 403);
        }

        $this->em->remove($club);
        $this->em->flush();

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