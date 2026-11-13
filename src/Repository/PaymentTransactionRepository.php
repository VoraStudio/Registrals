<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentTransaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentTransaction>
 */
class PaymentTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentTransaction::class);
    }

    public function findByStripeSessionId(string $sessionId): ?PaymentTransaction
    {
        return $this->findOneBy(['stripeSessionId' => $sessionId]);
    }

    public function findByStripePaymentIntentId(string $paymentIntentId): ?PaymentTransaction
    {
        return $this->findOneBy(['stripePaymentIntentId' => $paymentIntentId]);
    }

    public function findByStripeEventId(string $eventId): ?PaymentTransaction
    {
        return $this->findOneBy(['stripeEventId' => $eventId]);
    }

    /**
     * @return list<PaymentTransaction>
     */
    public function findByUser(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retorna les transaccions d'un usuari paginades per data descendent.
     *
     * @return list<PaymentTransaction>
     */
    public function findPaginatedByUser(User $user, int $page = 1, int $limit = 10): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);

        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recompte total de transaccions d'un usuari.
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
