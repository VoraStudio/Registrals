<?php

declare(strict_types=1);

namespace App\Audit\Repository;

use App\Audit\Entity\UserActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserActivityLog>
 */
class UserActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        $manager = $registry->getManager('audit');
        parent::__construct($registry, UserActivityLog::class);
    }

    /**
     * @return UserActivityLog[]
     */
    public function findRecentByUserUuid(string $userUuid, int $limit = 5): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.userUuid = :uuid')
            ->setParameter('uuid', $userUuid)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{items: UserActivityLog[], total: int, pages: int}
     */
    public function findPaginatedByUserUuid(string $userUuid, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $total = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.userUuid = :uuid')
            ->setParameter('uuid', $userUuid)
            ->getQuery()
            ->getSingleScalarResult();

        $items = $this->createQueryBuilder('l')
            ->andWhere('l.userUuid = :uuid')
            ->setParameter('uuid', $userUuid)
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * @return UserActivityLog[]
     */
    public function findPaginatedForAdmin(?string $search, ?string $action, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('l.userEmail LIKE :search OR l.userName LIKE :search OR l.description LIKE :search OR l.ipAddress LIKE :search')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        if ($action !== null && trim($action) !== '') {
            $qb->andWhere('l.action = :action')
                ->setParameter('action', trim($action));
        }

        return $qb->getQuery()->getResult();
    }

    public function countForAdmin(?string $search, ?string $action): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)');

        if ($search !== null && trim($search) !== '') {
            $qb->andWhere('l.userEmail LIKE :search OR l.userName LIKE :search OR l.description LIKE :search OR l.ipAddress LIKE :search')
                ->setParameter('search', '%' . trim($search) . '%');
        }

        if ($action !== null && trim($action) !== '') {
            $qb->andWhere('l.action = :action')
                ->setParameter('action', trim($action));
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
