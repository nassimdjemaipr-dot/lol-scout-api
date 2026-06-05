<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Player;
use App\Entity\User;
use App\Enum\PlayerRole;
use App\Repository\PlayerRepository;
use App\Service\RiotSyncService;
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
        private readonly RiotSyncService $riotSyncService,
    ) {
    }

    #[Route('', name: 'api_player_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        return $this->doSearch($request);
    }

    #[Route('/search', name: 'api_player_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        // Alias de list() pour retrocompatibilite.
        return $this->doSearch($request);
    }

    /**
     * Recherche/listing des joueurs avec filtres optionnels (role + isAvailable).
     */
    private function doSearch(Request $request): JsonResponse
    {
        $role = $request->query->get('role');
        $availableParam = $request->query->get('available');

        $criteria = [];

        if ($role !== null && $role !== '') {
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

        if ($availableParam !== null && $availableParam !== '') {
            $criteria['isAvailable'] = filter_var($availableParam, FILTER_VALIDATE_BOOLEAN);
        }

        $players = $criteria === []
            ? $this->playerRepository->findAll()
            : $this->playerRepository->findBy($criteria);

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

    /**
     * POST /api/players/me/riot-account
     * Lie un compte Riot au profil du joueur connecté.
     * Body : { "summonerName": "Pseudo#TAG", "region": "EUW1" }
     */
    #[Route('/me/riot-account', name: 'api_player_link_riot', methods: ['POST'])]
    public function linkRiotAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $player = $this->playerRepository->findOneBy(['user' => $user]);

        if ($player === null) {
            return $this->json(['error' => 'No player profile for this user'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || empty($data['summonerName']) || empty($data['region'])) {
            return $this->json(['error' => 'summonerName and region are required'], 400);
        }

        try {
            $riotAccount = $this->riotSyncService->linkRiotAccount(
                $player,
                (string) $data['summonerName'],
                (string) $data['region']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }

        return $this->json([
            'id' => $riotAccount->getId(),
            'summonerName' => $riotAccount->getSummonerName(),
            'puuid' => $riotAccount->getPuuid(),
            'region' => $riotAccount->getRegion(),
        ], 201);
    }


    /**
     * POST /api/players/me/sync-riot
     * Synchronise les stats Riot du joueur connecté.
     */
    #[Route('/me/sync-riot', name: 'api_player_sync_riot', methods: ['POST'])]
    public function syncRiotStats(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $player = $this->playerRepository->findOneBy(['user' => $user]);

        if ($player === null) {
            return $this->json(['error' => 'No player profile for this user'], 404);
        }

        $riotAccount = $this->em->getRepository(\App\Entity\RiotAccount::class)
            ->findOneBy(['player' => $player]);

        if ($riotAccount === null) {
            return $this->json(['error' => 'No Riot account linked. Use POST /api/players/me/riot-account first.'], 404);
        }

        try {
            $stats = $this->riotSyncService->syncStats($riotAccount);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 502);
        }

        return $this->json([
            'tier' => $stats->getTier(),
            'winrate' => $stats->getWinrate(),
            'rankedGamesCount' => $stats->getRankedGamesCount(),
            'lastSyncAt' => $riotAccount->getLastSyncAt()?->format(\DateTimeInterface::ATOM),
        ]);
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