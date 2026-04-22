<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RiotAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RiotAccount>
 */
class RiotAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RiotAccount::class);
    }
}
