<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Application;
use App\Entity\Club;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Application>
 */
class ApplicationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Application::class);
    }

    /**
     * Toutes les candidatures envoyées par un joueur.
     *
     * @return Application[]
     */
    public function findByPlayer(Player $player): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.player = :player')
            ->setParameter('player', $player)
            ->orderBy('a.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les candidatures reçues sur les offres d'un club.
     *
     * @return Application[]
     */
    public function findByClub(Club $club): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.offer', 'o')
            ->andWhere('o.club = :club')
            ->setParameter('club', $club)
            ->orderBy('a.appliedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
