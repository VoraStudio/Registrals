<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ItemDocument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ItemDocument>
 */
class ItemDocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemDocument::class);
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumTotalBytes(): int
    {
        $res = $this->createQueryBuilder('d')
            ->select('SUM(d.fileSizeBytes)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($res ?? 0);
    }
}
