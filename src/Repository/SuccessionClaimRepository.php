<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SuccessionClaim;
use App\Enum\SuccessionClaimStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuccessionClaim>
 */
class SuccessionClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuccessionClaim::class);
    }

    public function getNextClaimReference(): string
    {
        $year = (new \DateTimeImmutable())->format('Y');
        $prefix = sprintf('LEG-%s-', $year);

        $count = (int) $this->createQueryBuilder('sc')
            ->select('COUNT(sc.id)')
            ->andWhere('sc.claimReference LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return sprintf('%s%06d', $prefix, $count + 1);
    }

    public function countPendingHumanReview(): int
    {
        return (int) $this->createQueryBuilder('sc')
            ->select('COUNT(sc.id)')
            ->andWhere('sc.status = :status')
            ->setParameter('status', SuccessionClaimStatus::UNDER_HUMAN_REVIEW)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<SuccessionClaim>
     */
    public function findQueueOrdered(): array
    {
        return $this->createQueryBuilder('sc')
            ->orderBy('sc.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

