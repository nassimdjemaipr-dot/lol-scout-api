<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlayedChampion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlayedChampion>
 */
class PlayedChampionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayedChampion::class);
    }
}
