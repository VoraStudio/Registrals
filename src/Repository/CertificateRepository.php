<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Certificate>
 */
class CertificateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Certificate::class);
    }

    public function findByUuid(string|Uuid $uuid): ?Certificate
    {
        $uuidObj = is_string($uuid) ? Uuid::fromString($uuid) : $uuid;

        return $this->findOneBy(['uuid' => $uuidObj]);
    }

    public function findLatestByItem(Item $item, ?CertificateMode $mode = null): ?Certificate
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.item = :item')
            ->setParameter('item', $item)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(1);

        if ($mode !== null) {
            $qb->andWhere('c.mode = :mode')
                ->setParameter('mode', $mode);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * @return list<Certificate>
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.issuedBy = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Genera el següent número correlatiu de certificat (Ex: CERT-2026-000001).
     */
    public function getNextCertificateNumber(): string
    {
        $year = (new \DateTimeImmutable())->format('Y');
        $prefix = sprintf('CERT-%s-', $year);

        $count = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.certificateNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return sprintf('%s%06d', $prefix, $count + 1);
    }
}
