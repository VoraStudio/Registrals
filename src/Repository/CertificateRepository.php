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
     * Retorna tots els certificats on l'usuari és l'emissor O el propietari actual del bé,
     * amb suport per a filtres de text (número, nom del bé, CUI), tipus (Oficial / Esborrany) i estat.
     *
     * @return list<Certificate>
     */
    public function findForUser(
        User $user,
        ?string $search = null,
        ?string $mode = null,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->join('c.item', 'i')
            ->addSelect('i')
            ->andWhere('c.issuedBy = :user OR i.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('c.createdAt', 'DESC');

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $qb->andWhere('c.certificateNumber LIKE :search OR i.name LIKE :search OR i.cui LIKE :search')
               ->setParameter('search', $term);
        }

        if ($mode !== null && $mode !== '' && $mode !== 'all') {
            $qb->andWhere('c.mode = :mode')
               ->setParameter('mode', $mode);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Retorna mètriques consolidades dels certificats d'un usuari.
     *
     * @return array{total: int, official: int, draft: int, valid: int}
     */
    public function countMetricsForUser(User $user): array
    {
        $rows = $this->createQueryBuilder('c')
            ->join('c.item', 'i')
            ->select('c.mode, c.status, COUNT(c.id) as cnt')
            ->andWhere('c.issuedBy = :user OR i.owner = :user')
            ->setParameter('user', $user)
            ->groupBy('c.mode, c.status')
            ->getQuery()
            ->getArrayResult();

        $metrics = [
            'total' => 0,
            'official' => 0,
            'draft' => 0,
            'valid' => 0,
        ];

        foreach ($rows as $row) {
            $cnt = (int) $row['cnt'];
            $metrics['total'] += $cnt;

            $m = is_object($row['mode']) ? $row['mode']->value : (string) $row['mode'];
            $s = is_object($row['status']) ? $row['status']->value : (string) $row['status'];

            if ($m === CertificateMode::OFFICIAL->value) {
                $metrics['official'] += $cnt;
            } else {
                $metrics['draft'] += $cnt;
            }

            if ($s === CertificateStatus::VALID->value) {
                $metrics['valid'] += $cnt;
            }
        }

        return $metrics;
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
