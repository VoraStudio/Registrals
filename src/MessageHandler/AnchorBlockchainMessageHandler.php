<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\BlockchainReceipt;
use App\Infrastructure\Adapter\Blockchain\BlockchainAnchorInterface;
use App\Message\AnchorBlockchainMessage;
use App\Repository\ItemRepository;
use App\Service\ItemFingerprintCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Processador asíncron de missatges de notarització a Blockchain (Polygon PoS).
 */
#[AsMessageHandler]
final readonly class AnchorBlockchainMessageHandler
{
    public function __construct(
        private ItemRepository $itemRepository,
        private ItemFingerprintCalculator $fingerprintCalculator,
        private BlockchainAnchorInterface $anchorClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(AnchorBlockchainMessage $message): void
    {
        $item = $this->itemRepository->find($message->itemId);

        if (!$item) {
            $this->logger->error('Ítem no trobat per a la notarització a Blockchain', [
                'item_id' => $message->itemId,
                'action_type' => $message->actionType->value,
            ]);

            return;
        }

        try {
            // Càlcul del hash determinista si no s'ha proporcionat un hash específic
            $hashToAnchor = $message->customHash ?? $this->fingerprintCalculator->calculateForItem($item);

            $this->logger->info('Iniciant ancoratge asíncron a Polygon Blockchain', [
                'item_id' => $item->getId(),
                'cui' => $item->getCui(),
                'action_type' => $message->actionType->value,
                'hash' => $hashToAnchor,
            ]);

            // Crida a l'adaptador RPC de Polygon Layer 2
            $receiptDto = $this->anchorClient->anchorHash($item->getCui(), $hashToAnchor);

            // Persistència del rebut immutable a la base de dades
            $receipt = new BlockchainReceipt(
                item: $item,
                actionType: $message->actionType,
                contractAddress: $receiptDto->contractAddress,
                txHash: $receiptDto->txHash,
                blockNumber: $receiptDto->blockNumber,
                anchoredHash: $receiptDto->anchoredHash,
                network: $receiptDto->network,
                certificateId: $message->certificateId
            );

            $item->addBlockchainReceipt($receipt);
            $this->entityManager->persist($receipt);
            $this->entityManager->flush();

            $this->logger->info('Rebut de Blockchain desat amb èxit', [
                'receipt_id' => $receipt->getId(),
                'tx_hash' => $receipt->getTxHash(),
                'block_number' => $receipt->getBlockNumber(),
                'network' => $receipt->getNetwork(),
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error en processar la notarització a Blockchain: ' . $e->getMessage(), [
                'item_id' => $message->itemId,
                'exception' => $e,
            ]);

            throw $e;
        }
    }
}
