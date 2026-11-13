<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BillingConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BillingConfig>
 */
class BillingConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BillingConfig::class);
    }

    /**
     * Retorna la configuració fiscal activa vigent de la plataforma.
     */
    public function findActiveConfig(): ?BillingConfig
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('b.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retorna tots els perfils fiscals (actiu i històrics) ordenats per id descendent.
     *
     * @return list<BillingConfig>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

