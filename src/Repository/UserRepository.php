<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countBlocked(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countUsersByPlan(): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('p.code, COUNT(u.id) as userCount')
            ->join('u.plan', 'p')
            ->groupBy('p.code')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['code']] = (int) $row['userCount'];
        }

        return $result;
    }

    /**
     * @return list<User>
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.plan', 'p')
            ->addSelect('p')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<User>
     */
    public function searchAndFilter(?string $query, ?string $planCode, ?string $status, int $limit = 50, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('u')
            ->leftJoin('u.plan', 'p')
            ->addSelect('p')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($query !== null && trim($query) !== '') {
            $searchTerm = '%' . mb_strtolower(trim($query)) . '%';
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q OR LOWER(u.taxId) LIKE :q')
               ->setParameter('q', $searchTerm);
        }

        if ($planCode !== null && $planCode !== '') {
            $qb->andWhere('p.code = :planCode')
               ->setParameter('planCode', $planCode);
        }

        if ($status === 'active') {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', true);
        } elseif ($status === 'blocked') {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', false);
        }

        return $qb->getQuery()->getResult();
    }

    public function countSearchAndFilter(?string $query, ?string $planCode, ?string $status): int
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->leftJoin('u.plan', 'p');

        if ($query !== null && trim($query) !== '') {
            $searchTerm = '%' . mb_strtolower(trim($query)) . '%';
            $qb->andWhere('LOWER(u.email) LIKE :q OR LOWER(u.firstName) LIKE :q OR LOWER(u.lastName) LIKE :q OR LOWER(u.taxId) LIKE :q')
               ->setParameter('q', $searchTerm);
        }

        if ($planCode !== null && $planCode !== '') {
            $qb->andWhere('p.code = :planCode')
               ->setParameter('planCode', $planCode);
        }

        if ($status === 'active') {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', true);
        } elseif ($status === 'blocked') {
            $qb->andWhere('u.isActive = :isActive')->setParameter('isActive', false);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
