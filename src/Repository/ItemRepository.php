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

    /**
     * @return list<Item>
     */
    public function findPaginatedForAdmin(?string $search = null, ?string $status = null, int $page = 1, int $limit = 20, ?\App\Entity\User $owner = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.owner', 'o')
            ->addSelect('o')
            ->leftJoin('i.category', 'c')
            ->addSelect('c')
            ->orderBy('i.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($owner !== null) {
            $qb->andWhere('i.owner = :owner')
               ->setParameter('owner', $owner);
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('i.name LIKE :search OR i.cui LIKE :search OR i.brand LIKE :search OR i.model LIKE :search OR i.serialNumber LIKE :search OR o.email LIKE :search OR o.firstName LIKE :search OR o.lastName LIKE :search')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        if ($status !== null && trim($status) !== '') {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function countForAdmin(?string $search = null, ?string $status = null, ?\App\Entity\User $owner = null): int
    {
        $qb = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->leftJoin('i.owner', 'o');

        if ($owner !== null) {
            $qb->andWhere('i.owner = :owner')
               ->setParameter('owner', $owner);
        }

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('i.name LIKE :search OR i.cui LIKE :search OR i.brand LIKE :search OR i.model LIKE :search OR i.serialNumber LIKE :search OR o.email LIKE :search OR o.firstName LIKE :search OR o.lastName LIKE :search')
               ->setParameter('search', '%' . trim($search) . '%');
        }

        if ($status !== null && trim($status) !== '') {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $status);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
