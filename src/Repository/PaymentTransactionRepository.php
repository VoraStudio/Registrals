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
}
