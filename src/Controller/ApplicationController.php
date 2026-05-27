<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Application;
use App\Entity\User;
use App\Enum\ApplicationStatus;
use App\Repository\ApplicationRepository;
use App\Repository\ClubRepository;
use App\Repository\OfferRepository;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApplicationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ApplicationRepository $applicationRepository,
        private readonly OfferRepository $offerRepository,
        private readonly PlayerRepository $playerRepository,
        private readonly ClubRepository $clubRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * POST /api/applications
     * Un joueur authentifié postule à une offre.
     * Body : { "offerId": 42, "message": "Bonjour..." }
     */
    #[Route('/applications', name: 'api_application_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Seul un joueur peut postuler
        $player = $this->playerRepository->findOneBy(['user' => $user]);
        if ($player === null) {
            return $this->json(['error' => 'Only players can apply to offers'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $offerId = $data['offerId'] ?? null;
        if (!is_int($offerId) && !ctype_digit((string) $offerId)) {
            return $this->json(['error' => 'offerId is required and must be an integer'], 400);
        }

        $offer = $this->offerRepository->find((int) $offerId);
        if ($offer === null) {
            return $this->json(['error' => 'Offer not found'], 404);
        }
        if (!$offer->isActive()) {
            return $this->json(['error' => 'This offer is no longer active'], 409);
        }

        // Empêche les doubles candidatures (au niveau applicatif, en plus de la contrainte BDD)
        $existing = $this->applicationRepository->findOneBy(['player' => $player, 'offer' => $offer]);
        if ($existing !== null) {
            return $this->json(['error' => 'You already applied to this offer'], 409);
        }

        $application = new Application();
        $application->setPlayer($player);
        $application->setOffer($offer);
        $application->setMessage($data['message'] ?? null);

        $errors = $this->validator->validate($application);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $this->em->persist($application);
        $this->em->flush();

        return $this->json($application, 201, [], ['groups' => ['application:read']]);
    }

    /**
     * GET /api/applications/me
     * Liste les candidatures envoyées par le joueur authentifié.
     */
    #[Route('/applications/me', name: 'api_application_list_mine', methods: ['GET'])]
    public function listMine(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $player = $this->playerRepository->findOneBy(['user' => $user]);
        if ($player === null) {
            return $this->json(['error' => 'You must have a player profile'], 403);
        }

        $applications = $this->applicationRepository->findByPlayer($player);

        return $this->json($applications, 200, [], ['groups' => ['application:read']]);
    }

    /**
     * GET /api/clubs/me/applications
     * Liste les candidatures reçues sur les offres du club authentifié.
     */
    #[Route('/clubs/me/applications', name: 'api_application_list_for_club', methods: ['GET'])]
    public function listForClub(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $club = $this->clubRepository->findOneBy(['user' => $user]);
        if ($club === null) {
            return $this->json(['error' => 'You must have a club profile'], 403);
        }

        $applications = $this->applicationRepository->findByClub($club);

        return $this->json($applications, 200, [], ['groups' => ['application:read']]);
    }

    /**
     * PATCH /api/applications/{id}
     * Met à jour le statut (ACCEPTEE / REFUSEE).
     * Réservé au club propriétaire de l'offre.
     * Body : { "status": "ACCEPTEE" }
     */
    #[Route('/applications/{id}', name: 'api_application_update_status', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function updateStatus(Application $application, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Seul le club propriétaire de l'offre peut changer le statut
        $offerClub = $application->getOffer()?->getClub();
        if ($offerClub === null || $offerClub->getUser() !== $user) {
            return $this->json(['error' => 'Only the club owning the offer can update applications'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }

        $statusValue = $data['status'] ?? null;
        $status = is_string($statusValue) ? ApplicationStatus::tryFrom($statusValue) : null;
        if ($status === null) {
            return $this->json(
                [
                    'error' => 'Invalid status',
                    'allowed' => array_map(fn (ApplicationStatus $s) => $s->value, ApplicationStatus::cases()),
                ],
                400
            );
        }

        $application->setStatus($status);
        $this->em->flush();

        return $this->json($application, 200, [], ['groups' => ['application:read']]);
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
