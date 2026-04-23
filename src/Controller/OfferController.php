<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Offer;
use App\Entity\User;
use App\Enum\PlayerRole;
use App\Repository\ClubRepository;
use App\Repository\OfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/offers')]
class OfferController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OfferRepository $offerRepository,
        private readonly ClubRepository $clubRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'api_offer_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = ['isActive' => true];

        $roleParam = $request->query->get('role');
        if ($roleParam !== null) {
            $role = PlayerRole::tryFrom($roleParam);
            if ($role === null) {
                return $this->json(
                    [
                        'error' => 'Invalid role',
                        'allowed' => array_map(fn (PlayerRole $r) => $r->value, PlayerRole::cases()),
                    ],
                    400
                );
            }
            $criteria['wantedRole'] = $role;
        }

        $clubId = $request->query->get('club');
        if ($clubId !== null) {
            $criteria['club'] = (int) $clubId;
        }

        $offers = $this->offerRepository->findBy($criteria, ['publishedAt' => 'DESC']);

        return $this->json($offers, 200, [], ['groups' => ['offer:read']]);
    }

    #[Route('/{id}', name: 'api_offer_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(Offer $offer): JsonResponse
    {
        return $this->json($offer, 200, [], ['groups' => ['offer:read']]);
    }

    #[Route('', name: 'api_offer_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $club = $this->clubRepository->findOneBy(['user' => $user]);
        if ($club === null) {
            return $this->json(['error' => 'You must have a club profile to publish offers'], 403);
        }

        try {
            $offer = $this->serializer->deserialize(
                $request->getContent(),
                Offer::class,
                'json',
                ['groups' => ['offer:write']]
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid JSON: '.$e->getMessage()], 400);
        }

        $offer->setClub($club);

        $errors = $this->validator->validate($offer);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $this->em->persist($offer);
        $this->em->flush();

        return $this->json($offer, 201, [], ['groups' => ['offer:read']]);
    }

    #[Route('/{id}', name: 'api_offer_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function update(Offer $offer, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($offer->getClub()->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You can only edit offers from your own club'], 403);
        }

        try {
            $this->serializer->deserialize(
                $request->getContent(),
                Offer::class,
                'json',
                [
                    'groups' => ['offer:write'],
                    'object_to_populate' => $offer,
                ]
            );
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Invalid JSON: '.$e->getMessage()], 400);
        }

        $errors = $this->validator->validate($offer);
        if (count($errors) > 0) {
            return $this->json(['errors' => $this->formatErrors($errors)], 422);
        }

        $this->em->flush();

        return $this->json($offer, 200, [], ['groups' => ['offer:read']]);
    }

    #[Route('/{id}', name: 'api_offer_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Offer $offer): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($offer->getClub()->getUser() !== $user && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You can only delete offers from your own club'], 403);
        }

        $this->em->remove($offer);
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
