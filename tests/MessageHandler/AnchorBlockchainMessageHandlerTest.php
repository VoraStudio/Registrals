<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\DTO\BlockchainReceiptDto;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\BlockchainActionType;
use App\Infrastructure\Adapter\Blockchain\BlockchainAnchorInterface;
use App\Message\AnchorBlockchainMessage;
use App\MessageHandler\AnchorBlockchainMessageHandler;
use App\Repository\ItemRepository;
use App\Service\ItemFingerprintCalculator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AnchorBlockchainMessageHandlerTest extends TestCase
{
    public function testInvokeProcessesAndPersistsReceipt(): void
    {
        $user = new User('tester@registrals.cat', 'Tester Notarial');
        $category = new Category('Tecnologia', 'tecnologia');
        $item = new Item($user, 'REG-2026-TEST', 'MacBook Pro M3', $category);

        $itemRepository = $this->createMock(ItemRepository::class);
        $itemRepository->method('find')->with(1)->willReturn($item);

        $calculator = $this->createMock(ItemFingerprintCalculator::class);
        $calculator->method('calculateForItem')->with($item)->willReturn(str_repeat('a', 64));

        $anchorClient = $this->createMock(BlockchainAnchorInterface::class);
        $anchorClient->method('anchorHash')
            ->with('REG-2026-TEST', str_repeat('a', 64))
            ->willReturn(new BlockchainReceiptDto(
                txHash: '0x' . str_repeat('b', 64),
                blockNumber: 4500123,
                anchoredHash: str_repeat('a', 64),
                contractAddress: '0x1234567890123456789012345678901234567890',
                network: 'polygon-pos'
            ));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $handler = new AnchorBlockchainMessageHandler(
            itemRepository: $itemRepository,
            fingerprintCalculator: $calculator,
            anchorClient: $anchorClient,
            entityManager: $entityManager,
            logger: new NullLogger()
        );

        $message = new AnchorBlockchainMessage(itemId: 1, actionType: BlockchainActionType::ITEM_REGISTRATION);
        $handler->__invoke($message);

        $this->assertTrue($item->isBlockchainAnchored());
        $receipt = $item->getLatestBlockchainReceipt();
        $this->assertNotNull($receipt);
        $this->assertSame('0x' . str_repeat('b', 64), $receipt->getTxHash());
        $this->assertSame(4500123, $receipt->getBlockNumber());
        $this->assertSame(str_repeat('a', 64), $receipt->getAnchoredHash());
    }
}
