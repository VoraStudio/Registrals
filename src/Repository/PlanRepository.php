<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Plan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plan>
 */
class PlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plan::class);
    }

    public function findDefaultFreePlan(): ?Plan
    {
        return $this->findOneBy(['code' => 'free', 'isActive' => true]);
    }

    public function findByCode(string $code): ?Plan
    {
        return $this->findOneBy(['code' => $code, 'isActive' => true]);
    }

    /**
     * Indexa una col·lecció de plans pel seu codi (`free`, `plus`, `unlimited`).
     *
     * @param iterable<Plan> $plans
     * @return array<string, Plan>
     */
    public static function indexByCode(iterable $plans): array
    {
        $indexed = [];
        foreach ($plans as $plan) {
            $indexed[$plan->getCode()] = $plan;
        }

        return $indexed;
    }
}
