<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Player;
use App\Entity\User;
use App\Enum\PlayerRole;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/players')]
class PlayerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PlayerRepository $playerRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_player_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $players = $this->playerRepository->findAll();

        return $this->json(
            $players,
            200,
            [],
            ['groups' => ['player:read']]
        );
    }

    #[Route('/search', name: 'api_player_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $role = $request->query->get('role');
        $availableParam = $request->query->get('available');

        $criteria = [];

        if ($role !== null) {
            $roleEnum = PlayerRole::tryFrom($role);
            if ($roleEnum === null) {
                return $this->json(
                    [
                        'error' => 'Invalid role',
                        'allowed' => array_map(fn (PlayerRole $r) => $r->value, PlayerRole::cases()),
                    ],
                    400
                );
            }
            $criteria['gameRole'] = $roleEnum;
        }

        if ($availableParam !== null) {
            $criteria['isAvailable'] = filter_var($availableParam, FILTER_VALIDATE_BOOLEAN);
        }

        $players = $this->playerRepository->findBy($criteria);

        return $this->json(
            $players,
            200,
            [],
            ['groups' => ['player:read']]
        );
    }

    #[Route('/me', name: 'api_player_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $player = $this->playerRepository->findOneBy(['user' => $user]);

        if ($player === null) {
            return $this->json(['error' => 'No player profile for this user'], 404);
        }

        return $this->json(
            $player,
            200,
            [],
            ['groups' => ['player:read']]
        );
    }

    #[Route('/{id}', name: 'api_player_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(Player $player): JsonResponse
    {
        return $this->json(
            $player,
            200,
            [],
            ['groups' => ['player:read']]
        );
    }

    #[Route('', name: 'api_player_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $existing = $this->playerRepository->findOneBy(['user' => $user]);
        if ($existing !== null) {
            return $this->json(['error' => 'User already has a player profile'], 409);
        }

        try {
            $player = $this->serializer->deserialize(
                $request->getContent(),
                Player::class,
                'json',
                ['groups' => ['player:write']]
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid JSON: '.$e->getMessage()], 400);
        }

        $player->setUser($user);

        $errors = $this->validator->validate($player);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $this->em->persist($player);
        $this->em->flush();

        return $this->json(
            $player,
            201,
            [],
            ['groups' => ['player:read']]
        );
    }

    #[Route('/{id}', name: 'api_player_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(Player $player, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($player->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You can only edit your own player profile'], 403);
        }

        try {
            $this->serializer->deserialize(
                $request->getContent(),
                Player::class,
                'json',
                [
                    'groups' => ['player:write'],
                    'object_to_populate' => $player,
                ]
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid JSON: '.$e->getMessage()], 400);
        }

        $errors = $this->validator->validate($player);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $this->em->flush();

        return $this->json(
            $player,
            200,
            [],
            ['groups' => ['player:read']]
        );
    }

    #[Route('/{id}', name: 'api_player_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Player $player): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($player->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You can only delete your own player profile'], 403);
        }

        $this->em->remove($player);
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