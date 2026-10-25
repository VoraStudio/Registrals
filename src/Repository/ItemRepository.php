<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /**
     * Retorna els ítems principals (sense pare o col·leccions) d'un usuari.
     *
     * @return list<Item>
     */
    public function findTopLevelItemsByOwner(User $owner): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.category', 'c')
            ->addSelect('c')
            ->leftJoin('i.documents', 'd')
            ->addSelect('d')
            ->andWhere('i.owner = :owner')
            ->andWhere('i.parent IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCui(string $cui): ?Item
    {
        return $this->findOneBy(['cui' => $cui]);
    }

    public function countActiveByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.owner = :owner')
            ->andWhere('i.status = :status')
            ->setParameter('owner', $owner)
            ->setParameter('status', \App\Enum\ItemStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveTotal(): int
    {
        return (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->setParameter('status', \App\Enum\ItemStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Item>
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.owner', 'o')
            ->addSelect('o')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
