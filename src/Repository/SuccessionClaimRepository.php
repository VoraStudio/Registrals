<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LegacyContact;
use App\Entity\SuccessionClaim;
use App\Enum\SuccessionClaimStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
            ->leftJoin('sc.deceasedOwner', 'o')
            ->addSelect('o')
            ->leftJoin('sc.legacyContact', 'lc')
            ->addSelect('lc')
            ->orderBy('sc.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByUuid(string|Uuid $uuid): ?SuccessionClaim
    {
        $uuidObj = is_string($uuid) ? Uuid::fromString($uuid) : $uuid;

        return $this->findOneBy(['uuid' => $uuidObj]);
    }

    /**
     * @return list<SuccessionClaim>
     */
    public function findFilteredQueue(?string $search = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->leftJoin('sc.deceasedOwner', 'o')
            ->addSelect('o')
            ->leftJoin('sc.legacyContact', 'lc')
            ->addSelect('lc')
            ->orderBy('sc.createdAt', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $qb->andWhere(
                'sc.claimReference LIKE :term OR sc.claimantName LIKE :term OR sc.claimantEmail LIKE :term OR o.email LIKE :term OR o.firstName LIKE :term OR o.lastName LIKE :term'
            )->setParameter('term', $term);
        }

        if ($status !== null && trim($status) !== '' && $status !== 'all') {
            $enumStatus = SuccessionClaimStatus::tryFrom(trim($status));
            if ($enumStatus !== null) {
                $qb->andWhere('sc.status = :status')->setParameter('status', $enumStatus);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function findLatestByLegacyContact(LegacyContact $contact): ?SuccessionClaim
    {
        return $this->createQueryBuilder('sc')
            ->andWhere('sc.legacyContact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('sc.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestOpenByLegacyContact(LegacyContact $contact): ?SuccessionClaim
    {
        return $this->createQueryBuilder('sc')
            ->andWhere('sc.legacyContact = :contact')
            ->andWhere('sc.status NOT IN (:resolved)')
            ->setParameter('contact', $contact)
            ->setParameter('resolved', [SuccessionClaimStatus::APPROVED, SuccessionClaimStatus::REJECTED])
            ->orderBy('sc.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retorna l'últim expedient successori per cada contacte (indexat pel UUID RFC-4122 del LegacyContact).
     *
     * @param list<LegacyContact> $contacts
     *
     * @return array<string, SuccessionClaim>
     */
    public function findLatestByLegacyContacts(array $contacts): array
    {
        $persistedContacts = array_values(array_filter(
            $contacts,
            static fn (LegacyContact $c): bool => $c->getId() !== null
        ));

        if ($persistedContacts === []) {
            return [];
        }

        /** @var list<SuccessionClaim> $claims */
        $claims = $this->createQueryBuilder('sc')
            ->andWhere('sc.legacyContact IN (:contacts)')
            ->setParameter('contacts', $persistedContacts)
            ->orderBy('sc.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $byContactUuid = [];
        foreach ($claims as $claim) {
            $contact = $claim->getLegacyContact();
            if ($contact === null) {
                continue;
            }
            $uuid = $contact->getUuid()->toRfc4122();
            if (!isset($byContactUuid[$uuid])) {
                $byContactUuid[$uuid] = $claim;
            }
        }

        return $byContactUuid;
    }
}

