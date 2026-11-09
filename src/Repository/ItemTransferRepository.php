<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\User;
use App\Enum\TransferStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ItemTransfer>
 */
class ItemTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ItemTransfer::class);
    }

    public function findByToken(string $token): ?ItemTransfer
    {
        return $this->findOneBy(['token' => $token]);
    }

    public function findByUuid(string|Uuid $uuid): ?ItemTransfer
    {
        $uuidObj = is_string($uuid) ? Uuid::fromString($uuid) : $uuid;

        return $this->findOneBy(['uuid' => $uuidObj]);
    }

    public function findActiveTransferForItem(Item $item): ?ItemTransfer
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.item = :item')
            ->andWhere('t.status = :status')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('item', $item)
            ->setParameter('status', TransferStatus::PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<ItemTransfer>
     */
    public function findPendingSentByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.sender = :sender')
            ->andWhere('t.status = :status')
            ->setParameter('sender', $user)
            ->setParameter('status', TransferStatus::PENDING)
            ->orderBy('t.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ItemTransfer>
     */
    public function findPendingReceivedByEmail(string $email): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.recipientEmail = :email')
            ->andWhere('t.status = :status')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('email', strtolower(trim($email)))
            ->setParameter('status', TransferStatus::PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ItemTransfer>
     */
    public function findHistoryForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('(t.sender = :user OR t.recipient = :user OR t.recipientEmail = :email)')
            ->andWhere('t.status != :pending')
            ->setParameter('user', $user)
            ->setParameter('email', strtolower(trim((string) $user->getEmail())))
            ->setParameter('pending', TransferStatus::PENDING)
            ->orderBy('t.completedAt', 'DESC')
            ->addOrderBy('t.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ItemTransfer>
     */
    public function findHistoryReceivedForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('(t.recipient = :user OR (t.recipient IS NULL AND t.recipientEmail = :email))')
            ->andWhere('t.status != :pending')
            ->setParameter('user', $user)
            ->setParameter('email', strtolower(trim((string) $user->getEmail())))
            ->setParameter('pending', TransferStatus::PENDING)
            ->orderBy('t.completedAt', 'DESC')
            ->addOrderBy('t.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ItemTransfer>
     */
    public function findHistorySentForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.sender = :user')
            ->andWhere('t.status != :pending')
            ->setParameter('user', $user)
            ->setParameter('pending', TransferStatus::PENDING)
            ->orderBy('t.completedAt', 'DESC')
            ->addOrderBy('t.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retorna tots els traspassos pendents que han superat la data de venciment (72 hores).
     *
     * @return list<ItemTransfer>
     */
    public function findExpiredPendingTransfers(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.item', 'i')
            ->addSelect('i')
            ->andWhere('t.status = :status')
            ->andWhere('t.expiresAt <= :now')
            ->setParameter('status', TransferStatus::PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('t.expiresAt', 'ASC');

        if ($user !== null) {
            $qb->andWhere('(t.sender = :user OR t.recipient = :user OR LOWER(t.recipientEmail) = :email)')
               ->setParameter('user', $user)
               ->setParameter('email', strtolower(trim((string) $user->getEmail())));
        }

        return $qb->getQuery()->getResult();
    }
}
