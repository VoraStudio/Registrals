<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdCampaign;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdCampaign>
 */
class AdCampaignRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdCampaign::class);
    }

    /**
     * @return list<AdCampaign>
     */
    public function findCurrentlyActive(?DateTimeImmutable $now = null): array
    {
        $at = $now ?? new DateTimeImmutable();

        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.startsAt <= :now')
            ->andWhere('a.endsAt IS NULL OR a.endsAt >= :now')
            ->setParameter('active', true)
            ->setParameter('now', $at)
            ->orderBy('a.sortOrder', 'ASC')
            ->addOrderBy('a.startsAt', 'DESC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AdCampaign>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.sortOrder', 'ASC')
            ->addOrderBy('a.startsAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
