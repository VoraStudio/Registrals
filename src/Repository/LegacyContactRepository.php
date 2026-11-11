<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LegacyContact;
use App\Entity\User;
use App\Enum\LegacyContactStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegacyContact>
 */
class LegacyContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegacyContact::class);
    }

    /**
     * @return list<LegacyContact>
     */
    public function findByOwnerOrdered(User $owner): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('lc.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<LegacyContact>
     */
    public function findAcceptedByOwner(User $owner): array
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.owner = :owner')
            ->andWhere('lc.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', LegacyContactStatus::ACCEPTED)
            ->orderBy('lc.contactConsentedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countOccupiedSlotsByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('lc')
            ->select('COUNT(lc.id)')
            ->andWhere('lc.owner = :owner')
            ->andWhere('lc.status IN (:statuses)')
            ->setParameter('owner', $owner)
            ->setParameter('statuses', [
                LegacyContactStatus::PENDING,
                LegacyContactStatus::ACCEPTED,
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPendingByTokenHash(string $tokenHash): ?LegacyContact
    {
        return $this->createQueryBuilder('lc')
            ->andWhere('lc.invitationTokenHash = :hash')
            ->andWhere('lc.status = :status')
            ->setParameter('hash', $tokenHash)
            ->setParameter('status', LegacyContactStatus::PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

