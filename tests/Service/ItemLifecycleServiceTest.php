<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\BlockchainActionType;
use App\Enum\ItemStatus;
use App\Message\AnchorBlockchainMessage;
use App\Service\Item\ItemLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;

class ItemLifecycleServiceTest extends TestCase
{
    private function createRealStateMachine(): StateMachine
    {
        $builder = new DefinitionBuilder();
        $builder->addPlaces(['draft', 'active', 'transfer_pending', 'transferred', 'stolen', 'lost', 'archived']);

        $builder->addTransition(new Transition('publish', 'draft', 'active'));
        $builder->addTransition(new Transition('initiate_transfer', 'active', 'transfer_pending'));
        $builder->addTransition(new Transition('cancel_transfer', 'transfer_pending', 'active'));
        $builder->addTransition(new Transition('reject_transfer', 'transfer_pending', 'active'));
        $builder->addTransition(new Transition('expire_transfer', 'transfer_pending', 'active'));
        $builder->addTransition(new Transition('complete_transfer', 'transfer_pending', 'active'));
        $builder->addTransition(new Transition('archive_transferred', 'transfer_pending', 'transferred'));
        $builder->addTransition(new Transition('report_stolen', 'active', 'stolen'));
        $builder->addTransition(new Transition('report_stolen', 'transfer_pending', 'stolen'));
        $builder->addTransition(new Transition('recover_stolen', 'stolen', 'active'));
        $builder->addTransition(new Transition('report_lost', 'active', 'lost'));
        $builder->addTransition(new Transition('report_lost', 'transfer_pending', 'lost'));
        $builder->addTransition(new Transition('recover_lost', 'lost', 'active'));
        $builder->addTransition(new Transition('archive', 'active', 'archived'));
        $builder->addTransition(new Transition('archive', 'transferred', 'archived'));
        $builder->addTransition(new Transition('unarchive', 'archived', 'active'));

        $definition = $builder->build();
        $markingStore = new MethodMarkingStore(true, 'status');

        return new StateMachine($definition, $markingStore, null, 'item_lifecycle');
    }

    private function createItem(ItemStatus $status = ItemStatus::DRAFT): array
    {
        $owner = new User();
        $owner->setEmail('owner@registrals.cat');
        $owner->setFirstName('Owner');
        $owner->setLastName('Original');

        $category = new Category('Art', 'art');
        $item = new Item($owner, 'REG-2026-CHAGALL', 'Quadre Marc Chagall', $category);
        $item->setStatus($status);

        // Assignem un ID fictici mitjançant Reflection per a les proves de dispatch
        $refProp = new \ReflectionProperty(Item::class, 'id');
        $refProp->setValue($item, 42);

        return [$owner, $item];
    }

    public function testPublishDraftItem(): void
    {
        [, $item] = $this->createItem(ItemStatus::DRAFT);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new ItemLifecycleService(
            workflow: $this->createRealStateMachine(),
            entityManager: $em,
            logger: new NullLogger()
        );

        $this->assertTrue($service->can($item, 'publish'));
        $service->publish($item);

        $this->assertSame(ItemStatus::ACTIVE, $item->getStatus());
    }

    public function testTransferFlowAndOwnerChange(): void
    {
        [$ownerA, $item] = $this->createItem(ItemStatus::ACTIVE);

        $ownerB = new User();
        $ownerB->setEmail('buyer@registrals.cat');
        $ownerB->setFirstName('Buyer');
        $ownerB->setLastName('Nou');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->atLeastOnce())->method('flush');

        $dispatchedMessages = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message) use (&$dispatchedMessages) {
            $dispatchedMessages[] = $message;
            return new Envelope($message);
        });

        $service = new ItemLifecycleService(
            workflow: $this->createRealStateMachine(),
            entityManager: $em,
            messageBus: $bus,
            logger: new NullLogger()
        );

        // 1. Iniciar traspàs
        $this->assertTrue($service->can($item, 'initiate_transfer'));
        $service->initiateTransfer($item);
        $this->assertSame(ItemStatus::TRANSFER_PENDING, $item->getStatus());

        // 2. Completar traspàs i reassignar titularitat
        $this->assertTrue($service->can($item, 'complete_transfer'));
        $service->completeTransfer($item, $ownerB);

        $this->assertSame(ItemStatus::ACTIVE, $item->getStatus());
        $this->assertSame($ownerB, $item->getOwner());

        // Comprovem dispatch a blockchain
        $this->assertCount(1, $dispatchedMessages);
        $this->assertInstanceOf(AnchorBlockchainMessage::class, $dispatchedMessages[0]);
        $this->assertSame(BlockchainActionType::TRANSFER, $dispatchedMessages[0]->actionType);
    }

    public function testReportStolenDispatchesBlockchainAlert(): void
    {
        [, $item] = $this->createItem(ItemStatus::ACTIVE);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('flush');

        $dispatchedMessages = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message) use (&$dispatchedMessages) {
            $dispatchedMessages[] = $message;
            return new Envelope($message);
        });

        $service = new ItemLifecycleService(
            workflow: $this->createRealStateMachine(),
            entityManager: $em,
            messageBus: $bus,
            logger: new NullLogger()
        );

        $service->reportStolen($item);
        $this->assertSame(ItemStatus::STOLEN, $item->getStatus());

        $this->assertCount(1, $dispatchedMessages);
        $this->assertSame(BlockchainActionType::STOLEN_FLAG, $dispatchedMessages[0]->actionType);

        // Recuperar bé robat
        $this->assertTrue($service->can($item, 'recover_stolen'));
        $service->recoverStolen($item);
        $this->assertSame(ItemStatus::ACTIVE, $item->getStatus());
    }

    public function testInvalidTransitionThrowsLogicException(): void
    {
        [, $item] = $this->createItem(ItemStatus::DRAFT);

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new ItemLifecycleService(
            workflow: $this->createRealStateMachine(),
            entityManager: $em,
            logger: new NullLogger()
        );

        $this->assertFalse($service->can($item, 'initiate_transfer'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No es pot aplicar la transició "initiate_transfer"');

        $service->initiateTransfer($item);
    }
}
