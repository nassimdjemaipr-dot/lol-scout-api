<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Player;
use App\Enum\PlayerRole;
use App\Enum\Tier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Player>
 */
class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /**
     * Recherche multicritere. Tout critere nul est ignore.
     *
     * @return list<Player>
     */
    public function search(
        ?PlayerRole $gameRole = null,
        ?bool $isAvailable = null,
        ?Tier $minimumTier = null,
    ): array {
        $qb = $this->createQueryBuilder('p');

        if ($gameRole !== null) {
            $qb->andWhere('p.gameRole = :gameRole')
                ->setParameter('gameRole', $gameRole);
        }

        if ($isAvailable !== null) {
            $qb->andWhere('p.isAvailable = :isAvailable')
                ->setParameter('isAvailable', $isAvailable);
        }

        if ($minimumTier !== null) {
            // Jointure interne : un joueur sans compte Riot lie n'a pas de rang,
            // il ne peut donc pas satisfaire un critere de rang minimum.
            $qb->innerJoin('p.riotAccount', 'riotAccount')
                ->innerJoin('riotAccount.stats', 'stats');

            // Le rang est stocke sous la forme "Diamond II" : on compare sur le
            // prefixe, pour chacun des paliers acceptables.
            $acceptable = $qb->expr()->orX();

            foreach ($minimumTier->andAbove() as $index => $tier) {
                $acceptable->add($qb->expr()->like('stats.tier', ':tier' . $index));
                $qb->setParameter('tier' . $index, $tier->value . '%');
            }

            $qb->andWhere($acceptable);
        }

        $qb->orderBy('p.pseudo', 'ASC');

        /** @var list<Player> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
