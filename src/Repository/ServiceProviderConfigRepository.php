<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ServiceProviderConfig;
use App\Enum\ServiceProviderCategory;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceProviderConfig>
 */
class ServiceProviderConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceProviderConfig::class);
    }

    public function findActiveForCategory(ServiceProviderCategory $category, ?DateTimeImmutable $at = null): ?ServiceProviderConfig
    {
        $target = $at ?? new DateTimeImmutable();

        return $this->createQueryBuilder('s')
            ->andWhere('s.category = :category')
            ->andWhere('s.validFrom <= :target')
            ->andWhere('s.validTo IS NULL OR s.validTo >= :target')
            ->setParameter('category', $category)
            ->setParameter('target', $target)
            ->orderBy('s.validFrom', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<ServiceProviderConfig>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.category', 'ASC')
            ->addOrderBy('s.validFrom', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

