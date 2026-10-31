<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\User;
use App\Enum\TransferStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ItemTransferTest extends TestCase
{
    private function createItemAndSender(): array
    {
        $sender = new User();
        $sender->setEmail('sender@registrals.cat');
        $sender->setFirstName('Joan');
        $sender->setLastName('Vives');

        $category = new Category('Art', 'art');
        $item = new Item($sender, 'REG-2026-MIROR1', 'Escultura Miró', $category);

        return [$sender, $item];
    }

    public function testInitializationAndDefaultValues(): void
    {
        [$sender, $item] = $this->createItemAndSender();

        $transfer = new ItemTransfer(
            item: $item,
            sender: $sender,
            recipientEmail: 'Recipient@Registrals.cat ',
            transferFeeCents: 290,
            durationHours: 72,
            senderIp: '192.168.1.100',
            securityCode: '123456'
        );

        $this->assertNotNull($transfer->getUuid());
        $this->assertSame($item, $transfer->getItem());
        $this->assertSame($sender, $transfer->getSender());
        $this->assertSame('recipient@registrals.cat', $transfer->getRecipientEmail());
        $this->assertSame(TransferStatus::PENDING, $transfer->getStatus());
        $this->assertSame(290, $transfer->getTransferFeeCents());
        $this->assertSame('192.168.1.100', $transfer->getSenderIp());
        $this->assertSame('123456', $transfer->getSecurityCode());
        $this->assertSame(64, strlen($transfer->getToken()));
        $this->assertNull($transfer->getRecipient());
        $this->assertNull($transfer->getCompletedAt());
        $this->assertNull($transfer->getCancelledAt());
        $this->assertFalse($transfer->isExpired());
    }

    public function testCanBeAcceptedByRecipient(): void
    {
        [$sender, $item] = $this->createItemAndSender();

        $transfer = new ItemTransfer($item, $sender, 'buyer@example.com');

        $senderUser = new User();
        $senderUser->setEmail('sender@registrals.cat');

        $wrongUser = new User();
        $wrongUser->setEmail('other@example.com');

        $buyerUser = new User();
        $buyerUser->setEmail('buyer@example.com');

        $this->assertFalse($transfer->canBeAcceptedBy($sender));
        $this->assertFalse($transfer->canBeAcceptedBy($wrongUser));
        $this->assertTrue($transfer->canBeAcceptedBy($buyerUser));
    }

    public function testAcceptTransfer(): void
    {
        [$sender, $item] = $this->createItemAndSender();

        $transfer = new ItemTransfer($item, $sender, 'buyer@example.com');

        $buyer = new User();
        $buyer->setEmail('buyer@example.com');

        $transfer->accept($buyer, '10.0.0.5');

        $this->assertSame(TransferStatus::ACCEPTED, $transfer->getStatus());
        $this->assertSame($buyer, $transfer->getRecipient());
        $this->assertSame('10.0.0.5', $transfer->getRecipientIp());
        $this->assertInstanceOf(DateTimeImmutable::class, $transfer->getCompletedAt());
        $this->assertTrue($transfer->getStatus()->isCompleted());
        $this->assertTrue($transfer->getStatus()->isTerminated());
    }

    public function testRejectTransfer(): void
    {
        [$sender, $item] = $this->createItemAndSender();

        $transfer = new ItemTransfer($item, $sender, 'buyer@example.com');
        $transfer->reject('Preu no convingut', '10.0.0.9');

        $this->assertSame(TransferStatus::REJECTED, $transfer->getStatus());
        $this->assertSame('Preu no convingut', $transfer->getRejectionReason());
        $this->assertSame('10.0.0.9', $transfer->getRecipientIp());
        $this->assertNotNull($transfer->getCompletedAt());
        $this->assertTrue($transfer->getStatus()->isTerminated());
    }

    public function testCancelTransfer(): void
    {
        [$sender, $item] = $this->createItemAndSender();

        $transfer = new ItemTransfer($item, $sender, 'buyer@example.com');
        $transfer->cancel('192.168.1.100');

        $this->assertSame(TransferStatus::CANCELLED, $transfer->getStatus());
        $this->assertNotNull($transfer->getCancelledAt());
        $this->assertTrue($transfer->getStatus()->isTerminated());
    }

    public function testExpirationLogic(): void
    {
        [$sender, $item] = $this->createItemAndSender();

        $transfer = new ItemTransfer($item, $sender, 'buyer@example.com', 290, 72);
        $this->assertFalse($transfer->isExpired());

        // Set expiresAt to the past
        $transfer->setExpiresAt(new DateTimeImmutable('-1 hour'));
        $this->assertTrue($transfer->isExpired());

        $buyer = new User();
        $buyer->setEmail('buyer@example.com');
        $this->assertFalse($transfer->canBeAcceptedBy($buyer));
    }

    public function testItemActiveTransferRelationship(): void
    {
        [$sender, $item] = $this->createItemAndSender();

        $this->assertNull($item->getActiveTransfer());

        $transfer = new ItemTransfer($item, $sender, 'buyer@example.com');
        $item->addTransfer($transfer);

        $this->assertCount(1, $item->getTransfers());
        $this->assertSame($transfer, $item->getActiveTransfer());

        // Once completed, it is no longer the active pending transfer
        $buyer = new User();
        $buyer->setEmail('buyer@example.com');
        $transfer->accept($buyer);

        $this->assertNull($item->getActiveTransfer());
    }
}
