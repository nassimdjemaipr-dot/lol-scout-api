<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\ApplicationRepository;
use App\Repository\ClubRepository;
use App\Repository\OfferRepository;
use App\Repository\PlayerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace d'administration.
 *
 * L'acces est refuse en amont par security.yaml, qui exige ROLE_ADMIN sur tout
 * le prefixe /api/admin. Les controles presents ici portent sur autre chose :
 * empecher un administrateur de se verrouiller lui-meme dehors.
 */
#[Route('/api/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly ClubRepository $clubRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly OfferRepository $offerRepository,
        private readonly ApplicationRepository $applicationRepository,
    ) {
    }

    #[Route('/stats', name: 'api_admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $users = $this->userRepository->findAll();
        $clubs = $this->clubRepository->findAll();

        $byRole = [];
        foreach (UserRole::cases() as $role) {
            $byRole[$role->value] = 0;
        }

        $inactiveUsers = 0;
        foreach ($users as $user) {
            $roleValue = $user->getRole()?->value;
            if ($roleValue !== null) {
                ++$byRole[$roleValue];
            }
            if (!$user->isActive()) {
                ++$inactiveUsers;
            }
        }

        $unverifiedClubs = 0;
        foreach ($clubs as $club) {
            if (!$club->isVerified()) {
                ++$unverifiedClubs;
            }
        }

        return $this->json([
            'users' => [
                'total' => count($users),
                'inactive' => $inactiveUsers,
                'byRole' => $byRole,
            ],
            'clubs' => [
                'total' => count($clubs),
                'unverified' => $unverifiedClubs,
            ],
            'players' => count($this->playerRepository->findAll()),
            'offers' => count($this->offerRepository->findAll()),
            'applications' => count($this->applicationRepository->findAll()),
        ]);
    }

    #[Route('/users', name: 'api_admin_users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $roleParam = $request->query->get('role');
        $activeParam = $request->query->get('active');

        $criteria = [];

        if ($roleParam !== null && $roleParam !== '') {
            $role = UserRole::tryFrom($roleParam);
            if ($role === null) {
                return $this->json(
                    [
                        'error' => 'Invalid role',
                        'allowed' => array_map(fn (UserRole $r) => $r->value, UserRole::cases()),
                    ],
                    400
                );
            }
            $criteria['role'] = $role;
        }

        if ($activeParam !== null && $activeParam !== '') {
            $criteria['isActive'] = filter_var($activeParam, FILTER_VALIDATE_BOOLEAN);
        }

        $users = $this->userRepository->findBy($criteria, ['createdAt' => 'DESC']);

        return $this->json(array_map(fn (User $user) => $this->serializeUser($user), $users));
    }

    #[Route('/users/{id}/status', name: 'api_admin_user_status', methods: ['PATCH'])]
    public function updateUserStatus(User $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !array_key_exists('isActive', $data) || !is_bool($data['isActive'])) {
            return $this->json(['error' => 'Field isActive is required and must be a boolean'], 400);
        }

        // Un administrateur qui se desactive lui-meme n'a plus aucun moyen de
        // revenir : la desactivation bloque la connexion (voir UserChecker).
        if ($user === $this->getUser() && $data['isActive'] === false) {
            return $this->json(['error' => 'You cannot deactivate your own account'], 409);
        }

        $user->setIsActive($data['isActive']);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json($this->serializeUser($user));
    }

    #[Route('/clubs', name: 'api_admin_clubs', methods: ['GET'])]
    public function clubs(Request $request): JsonResponse
    {
        $verifiedParam = $request->query->get('verified');

        $criteria = [];
        if ($verifiedParam !== null && $verifiedParam !== '') {
            $criteria['isVerified'] = filter_var($verifiedParam, FILTER_VALIDATE_BOOLEAN);
        }

        $clubs = $this->clubRepository->findBy($criteria, ['name' => 'ASC']);

        return $this->json(array_map(fn (Club $club) => $this->serializeClub($club), $clubs));
    }

    #[Route('/clubs/{id}/verify', name: 'api_admin_club_verify', methods: ['PATCH'])]
    public function verifyClub(Club $club, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !array_key_exists('isVerified', $data) || !is_bool($data['isVerified'])) {
            return $this->json(['error' => 'Field isVerified is required and must be a boolean'], 400);
        }

        $club->setIsVerified($data['isVerified']);
        $this->em->flush();

        return $this->json($this->serializeClub($club));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        // Serialisation manuelle plutot que par groupes : l'entite User n'expose
        // aucun groupe de lecture, et c'est precisement ce qui garantit que son
        // empreinte de mot de passe ne peut pas fuir par inadvertance.
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->getRole()?->value,
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeClub(Club $club): array
    {
        return [
            'id' => $club->getId(),
            'name' => $club->getName(),
            'description' => $club->getDescription(),
            'website' => $club->getWebsite(),
            'isVerified' => $club->isVerified(),
            'owner' => [
                'id' => $club->getUser()?->getId(),
                'email' => $club->getUser()?->getEmail(),
                'isActive' => $club->getUser()?->isActive(),
            ],
        ];
    }
}
