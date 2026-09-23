<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\Player;
use App\Entity\User;
use App\Repository\ClubRepository;
use App\Repository\OfferRepository;
use App\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AccountAnonymizer
{
    public const DELETED_LABEL = 'Compte supprimé';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly PlayerRepository $playerRepository,
        private readonly ClubRepository $clubRepository,
        private readonly OfferRepository $offerRepository,
    ) {
    }

    public function anonymize(User $user): void
    {
        $player = $this->playerRepository->findOneBy(['user' => $user]);
        if ($player !== null) {
            $this->anonymizePlayer($player);
        }

        $club = $this->clubRepository->findOneBy(['user' => $user]);
        if ($club !== null) {
            $this->anonymizeClub($club);
        }

        $user->setEmail(sprintf('deleted-%d@lolscout.invalid', $user->getId()));
        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));
        $user->setIsActive(false);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();
    }

    private function anonymizePlayer(Player $player): void
    {
        $player->setPseudo(self::DELETED_LABEL);
        $player->setFirstName(self::DELETED_LABEL);
        $player->setLastName(self::DELETED_LABEL);
        $player->setBio(null);
        $player->setIsAvailable(false);

        $riotAccount = $player->getRiotAccount();
        if ($riotAccount !== null) {
            $riotAccount->setSummonerName(self::DELETED_LABEL);
            $riotAccount->setPuuid(sprintf('deleted-%d', $riotAccount->getId()));
            $riotAccount->setLastSyncAt(null);
        }
    }

    private function anonymizeClub(Club $club): void
    {
        $club->setName(self::DELETED_LABEL);
        $club->setDescription(null);
        $club->setLogoUrl(null);
        $club->setWebsite(null);
        $club->setIsVerified(false);

        foreach ($this->offerRepository->findBy(['club' => $club]) as $offer) {
            $offer->setIsActive(false);
        }
    }
}
