<?php

declare(strict_types=1);

namespace App\Service\Transfer;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\ItemTransfer;
use App\Entity\User;
use App\Enum\ItemStatus;
use App\Repository\ItemTransferRepository;
use App\Service\Item\ItemLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Servei de domini responsable de la caducitat automàtica dels traspassos C2C
 * que han superat la finestra de 72 hores de reserva.
 */
class TransferExpirationService
{
    public function __construct(
        private readonly ItemTransferRepository $itemTransferRepository,
        private readonly ItemLifecycleService $lifecycleService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Caduca tots els traspassos pendents vençuts (> 72 hores).
     * Opcionalment filtrats per a un usuari concret (comprovació Just-In-Time).
     *
     * @return int Nombre de traspassos caducats i conciliats
     */
    public function expireTransfers(?User $user = null): int
    {
        $expiredTransfers = $this->itemTransferRepository->findExpiredPendingTransfers($user);

        if (empty($expiredTransfers)) {
            return 0;
        }

        $count = 0;
        foreach ($expiredTransfers as $transfer) {
            $this->expireSingleTransfer($transfer);
            ++$count;
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Executa la caducitat atòmica d'un traspàs concret:
     * 1. Marca el traspàs com a EXPIRED.
     * 2. Aplica la transició de Workflow per retornar el bé a ACTIVE.
     * 3. Notifica per correu electrònic a l'emissor original.
     * 4. Enregistra l'acció a l'auditoria forense.
     */
    public function expireSingleTransfer(ItemTransfer $transfer): void
    {
        $transfer->expire();
        $item = $transfer->getItem();
        $sender = $transfer->getSender();

        if ($item->getStatus() === ItemStatus::TRANSFER_PENDING) {
            $this->lifecycleService->expireTransfer($item);
        }

        // Enviament del correu transaccional certificat d'avís de caducitat
        try {
            $itemUrl = $this->urlGenerator->generate(
                'app_item_show',
                ['cui' => $item->getCui()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('notaria@registrals.com', 'Notaria Digital Registrals'))
                ->to(new Address($sender->getEmail(), $sender->getFullName()))
                ->subject(sprintf('Caducitat de Traspàs: %s (%s)', $item->getName(), $item->getCui()))
                ->htmlTemplate('emails/transfer_expired.html.twig')
                ->context([
                    'sender' => $sender,
                    'item' => $item,
                    'transfer' => $transfer,
                    'itemUrl' => $itemUrl,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error en enviar correu de caducitat de traspàs: ' . $e->getMessage(), [
                'transfer_id' => $transfer->getId(),
                'cui' => $item->getCui(),
                'sender' => $sender->getEmail(),
            ]);
        }

        // Registre forense a registrals_audit
        $this->activityLogger?->log(
            user: $sender,
            action: UserActivityAction::TRANSFER_EXPIRED,
            description: sprintf('Caducitat automàtica del traspàs del bé %s (72 hores superades)', $item->getCui()),
            details: [
                'cui' => $item->getCui(),
                'item_name' => $item->getName(),
                'recipient_email' => $transfer->getRecipientEmail(),
                'requested_at' => $transfer->getRequestedAt()->format(\DateTimeInterface::ATOM),
                'expires_at' => $transfer->getExpiresAt()->format(\DateTimeInterface::ATOM),
            ]
        );

        $this->logger?->info('Traspàs caducat correctament', [
            'transfer_id' => $transfer->getId(),
            'cui' => $item->getCui(),
            'sender' => $sender->getEmail(),
            'recipient' => $transfer->getRecipientEmail(),
        ]);
    }
}

