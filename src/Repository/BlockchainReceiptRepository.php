<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlockchainReceipt;
use App\Entity\Item;
use App\Enum\BlockchainActionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlockchainReceipt>
 */
class BlockchainReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlockchainReceipt::class);
    }

    /**
     * Retorna el darrer rebut de notarització d'un ítem.
     */
    public function findLatestByItem(Item $item): ?BlockchainReceipt
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.item = :item')
            ->setParameter('item', $item)
            ->orderBy('r.anchoredAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retorna el rebut d'alta inicial d'un ítem si existeix.
     */
    public function findRegistrationReceipt(Item $item): ?BlockchainReceipt
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.item = :item')
            ->andWhere('r.actionType = :action')
            ->setParameter('item', $item)
            ->setParameter('action', BlockchainActionType::ITEM_REGISTRATION)
            ->orderBy('r.anchoredAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retorna l'històric de rebuts ordenats cronològicament.
     *
     * @return list<BlockchainReceipt>
     */
    public function findHistoryByItem(Item $item): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.item = :item')
            ->setParameter('item', $item)
            ->orderBy('r.anchoredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
