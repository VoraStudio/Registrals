<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Item;
use App\Enum\ItemStatus;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

class ItemLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.item_lifecycle.guard.initiate_transfer' => ['guardInitiateTransfer'],
            'workflow.item_lifecycle.transition' => ['onTransition'],
            'workflow.item_lifecycle.entered' => ['onEntered'],
        ];
    }

    public function guardInitiateTransfer(GuardEvent $event): void
    {
        $item = $event->getSubject();
        if (!$item instanceof Item) {
            return;
        }

        // Un bé que no està actiu o que està denunciat com a robat mai pot iniciar un traspàs
        if ($item->getStatus() !== ItemStatus::ACTIVE) {
            $event->setBlocked(true, 'Només els béns actius i lliures de càrrega o denúncia poden ser traspassats.');
        }
    }

    public function onTransition(TransitionEvent $event): void
    {
        $item = $event->getSubject();
        if (!$item instanceof Item) {
            return;
        }

        $transition = $event->getTransition();
        $this->logger?->info('Executant transició de cicle de vida del bé', [
            'cui' => $item->getCui(),
            'transition' => $transition->getName(),
            'from' => $transition->getFroms(),
            'to' => $transition->getTos(),
        ]);
    }

    public function onEntered(EnteredEvent $event): void
    {
        $item = $event->getSubject();
        if (!$item instanceof Item) {
            return;
        }

        $this->logger?->info('Bé ha entrat en nou estat de cicle de vida', [
            'cui' => $item->getCui(),
            'status' => $item->getStatus()->value,
        ]);
    }
}
