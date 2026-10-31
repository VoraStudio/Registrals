<?php

declare(strict_types=1);

namespace App\Service\Item;

use App\Entity\Item;
use App\Entity\User;
use App\Enum\BlockchainActionType;
use App\Message\AnchorBlockchainMessage;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Servei de gestió de cicle de vida i transicions d'estat dels béns (Symfony Workflow).
 */
class ItemLifecycleService
{
    public function __construct(
        #[Target('item_lifecycle')]
        private readonly WorkflowInterface $workflow,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?MessageBusInterface $messageBus = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function can(Item $item, string $transition): bool
    {
        return $this->workflow->can($item, $transition);
    }

    /**
     * @return list<string>
     */
    public function getEnabledTransitions(Item $item): array
    {
        $enabled = [];
        foreach ($this->workflow->getEnabledTransitions($item) as $transition) {
            $enabled[] = $transition->getName();
        }

        return $enabled;
    }

    public function apply(Item $item, string $transition): void
    {
        if (!$this->can($item, $transition)) {
            throw new LogicException(sprintf(
                'No es pot aplicar la transició "%s" al bé %s en el seu estat actual (%s).',
                $transition,
                $item->getCui(),
                $item->getStatus()->value
            ));
        }

        $this->workflow->apply($item, $transition);
        $this->entityManager->flush();

        $this->logger?->info('Transició de cicle de vida aplicada al bé', [
            'cui' => $item->getCui(),
            'transition' => $transition,
            'new_status' => $item->getStatus()->value,
        ]);
    }

    public function publish(Item $item): void
    {
        $this->apply($item, 'publish');
    }

    public function initiateTransfer(Item $item): void
    {
        $this->apply($item, 'initiate_transfer');
    }

    public function cancelTransfer(Item $item): void
    {
        $this->apply($item, 'cancel_transfer');
    }

    public function rejectTransfer(Item $item): void
    {
        $this->apply($item, 'reject_transfer');
    }

    public function expireTransfer(Item $item): void
    {
        $this->apply($item, 'expire_transfer');
    }

    /**
     * Completa el traspàs C2C transmetent la titularitat al nou propietari.
     */
    public function completeTransfer(Item $item, User $newOwner): void
    {
        $previousOwner = $item->getOwner();

        $this->apply($item, 'complete_transfer');
        $item->setOwner($newOwner);
        $this->entityManager->flush();

        // Enviament de la transacció de traspàs a Polygon Blockchain (Layer 2)
        if ($item->getId() !== null) {
            $this->messageBus?->dispatch(new AnchorBlockchainMessage(
                $item->getId(),
                BlockchainActionType::TRANSFER
            ));
        }

        $this->logger?->info('Traspàs C2C completat amb èxit', [
            'cui' => $item->getCui(),
            'previous_owner' => $previousOwner->getEmail(),
            'new_owner' => $newOwner->getEmail(),
        ]);
    }

    public function reportStolen(Item $item): void
    {
        $this->apply($item, 'report_stolen');

        // Notarització immediata de la denúncia per sostracció a Blockchain
        if ($item->getId() !== null) {
            $this->messageBus?->dispatch(new AnchorBlockchainMessage(
                $item->getId(),
                BlockchainActionType::STOLEN_FLAG
            ));
        }
    }

    public function recoverStolen(Item $item): void
    {
        $this->apply($item, 'recover_stolen');
    }

    public function reportLost(Item $item): void
    {
        $this->apply($item, 'report_lost');
    }

    public function recoverLost(Item $item): void
    {
        $this->apply($item, 'recover_lost');
    }

    public function archive(Item $item): void
    {
        $this->apply($item, 'archive');
    }

    public function unarchive(Item $item): void
    {
        $this->apply($item, 'unarchive');
    }
}
