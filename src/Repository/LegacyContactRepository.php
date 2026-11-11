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

    /**
     * Retorna les designacions rebudes per un usuari (per compte vinculat o correu electrònic)
     * en estat pendent o acceptat, sense exposar dades del patrimoni del titular.
     *
     * @return list<LegacyContact>
     */
    public function findIncomingActiveOrPendingForUser(User $user): array
    {
        $normalizedEmail = mb_strtolower(trim($user->getEmail()));

        return $this->createQueryBuilder('lc')
            ->leftJoin('lc.owner', 'o')
            ->addSelect('o')
            ->andWhere('lc.beneficiaryUser = :user OR lc.email = :email')
            ->andWhere('lc.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('email', $normalizedEmail)
            ->setParameter('statuses', [
                LegacyContactStatus::PENDING,
                LegacyContactStatus::ACCEPTED,
            ])
            ->orderBy('lc.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

