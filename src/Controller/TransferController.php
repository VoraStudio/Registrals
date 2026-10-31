<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\User;
use App\Enum\TransferStatus;
use App\Repository\ItemRepository;
use App\Repository\ItemTransferRepository;
use App\Security\Voter\ItemVoter;
use App\Service\Item\ItemLifecycleService;
use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controlador de gestió de traspassos C2C de titularitat de béns mobles.
 */
class TransferController extends AbstractController
{
    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly ItemTransferRepository $itemTransferRepository,
        private readonly ItemLifecycleService $lifecycleService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Safata de gestió de traspassos de l'usuari (Rebuts i Enviats).
     */
    #[Route('/app/transfers', name: 'app_transfers_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $pendingReceived = $this->itemTransferRepository->findPendingReceivedByEmail($user->getEmail());
        $pendingSent = $this->itemTransferRepository->findPendingSentByUser($user);
        $historyReceived = $this->itemTransferRepository->findHistoryReceivedForUser($user);
        $historySent = $this->itemTransferRepository->findHistorySentForUser($user);

        return $this->render('transfer/index.html.twig', [
            'pendingReceived' => $pendingReceived,
            'pendingSent' => $pendingSent,
            'historyReceived' => $historyReceived,
            'historySent' => $historySent,
            'transferHistory' => array_merge($historyReceived, $historySent),
        ]);
    }

    /**
     * Inicia una sol·licitud notarial de traspàs per correu electrònic.
     */
    #[Route('/app/items/{cui}/transfer/initiate', name: 'app_item_transfer_initiate', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function initiate(string $cui, Request $request): Response
    {
        $item = $this->itemRepository->findOneBy(['cui' => $cui]);
        if (!$item) {
            throw $this->createNotFoundException(sprintf('No s\'ha trobat el bé amb CUI "%s".', $cui));
        }

        $this->denyAccessUnlessGranted(ItemVoter::TRANSFER, $item);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('initiate_transfer_' . $item->getId(), $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid. Si us plau, torna-ho a provar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
        }

        // Comprovació de traspàs actiu previ
        if ($item->getActiveTransfer() !== null) {
            $this->addFlash('warning', 'Aquest bé ja disposa d\'un traspàs en curs.');
            return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
        }

        $recipientEmail = strtolower(trim((string) $request->request->get('recipient_email', '')));
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'L\'adreça electrònica del receptor no és vàlida.');
            return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
        }

        if ($recipientEmail === strtolower($currentUser->getEmail())) {
            $this->addFlash('error', 'No podeu traspassar un bé a la vostra pròpia adreça de correu.');
            return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
        }

        $rawSecurityCode = trim((string) $request->request->get('security_code', ''));
        $securityCode = $rawSecurityCode !== '' ? $rawSecurityCode : null;

        // Càlcul de taxa segons el pla del remitent
        $plan = $currentUser->getPlan();
        $transfersIncluded = $plan?->getTransfersIncluded() ?? 0;
        $transferFeeCents = $transfersIncluded > 0 ? 0 : ($plan?->getExtraTransferPriceCents() ?? 290);

        $transfer = new ItemTransfer(
            item: $item,
            sender: $currentUser,
            recipientEmail: $recipientEmail,
            transferFeeCents: $transferFeeCents,
            durationHours: 72,
            senderIp: $request->getClientIp(),
            securityCode: $securityCode
        );

        $this->lifecycleService->initiateTransfer($item);
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        // Enviament de la invitació per correu
        try {
            $claimUrl = $this->generateUrl(
                'app_transfer_claim',
                ['token' => $transfer->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address('notaria@registrals.com', 'Notaria Digital Registrals'))
                ->to(new Address($recipientEmail))
                ->subject(sprintf('Invitació de Traspàs Notarial: %s (%s)', $item->getName(), $item->getCui()))
                ->htmlTemplate('emails/transfer_invitation.html.twig')
                ->context([
                    'sender' => $currentUser,
                    'item' => $item,
                    'transfer' => $transfer,
                    'claimUrl' => $claimUrl,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error en enviar el correu electrònic de traspàs: ' . $e->getMessage(), [
                'cui' => $item->getCui(),
                'recipient' => $recipientEmail,
            ]);
        }

        // Registre a la base d'auditoria forense (registrals_audit)
        $this->activityLogger?->log(
            user: $currentUser,
            action: UserActivityAction::TRANSFER_INITIATED,
            description: sprintf('Inici de traspàs de titularitat del bé %s a %s', $item->getCui(), $recipientEmail),
            details: [
                'cui' => $item->getCui(),
                'item_name' => $item->getName(),
                'recipient_email' => $recipientEmail,
                'fee_cents' => $transferFeeCents,
                'has_security_code' => $securityCode !== null,
                'token' => substr($transfer->getToken(), 0, 12) . '...',
            ]
        );

        $this->addFlash('success', sprintf(
            'Sol·licitud de traspàs notarial iniciada correctament. S\'ha enviat una invitació a %s vàlida durant 72 hores.',
            $recipientEmail
        ));

        return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
    }

    /**
     * Cancel·la una sol·licitud activa de traspàs per voluntat de l'emissor.
     */
    #[Route('/app/items/{cui}/transfer/cancel', name: 'app_item_transfer_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(string $cui, Request $request): Response
    {
        $item = $this->itemRepository->findOneBy(['cui' => $cui]);
        if (!$item) {
            throw $this->createNotFoundException(sprintf('No s\'ha trobat el bé amb CUI "%s".', $cui));
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        if ($item->getOwner()->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Només el propietari pot cancel·lar el traspàs d\'aquest bé.');
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('cancel_transfer_' . $item->getId(), $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');
            return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
        }

        $activeTransfer = $item->getActiveTransfer();
        if ($activeTransfer) {
            $recipientEmail = $activeTransfer->getRecipientEmail();
            $activeTransfer->cancel($request->getClientIp());
            $this->lifecycleService->cancelTransfer($item);
            $this->entityManager->flush();

            // Registre a la base d'auditoria forense
            $this->activityLogger?->log(
                user: $currentUser,
                action: UserActivityAction::TRANSFER_CANCELLED,
                description: sprintf('Cancel·lació de la sol·licitud de traspàs del bé %s', $item->getCui()),
                details: [
                    'cui' => $item->getCui(),
                    'recipient_email' => $recipientEmail,
                ]
            );

            $this->addFlash('info', 'El traspàs ha estat cancel·lat correctament. El bé continua custodiat al vostre patrimoni.');
        } else {
            $this->addFlash('warning', 'No s\'ha trobat cap traspàs pendent per a aquest bé.');
        }

        return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
    }

    /**
     * Serveix la fotografia principal del bé per a la fitxa de traspàs de forma protegida mitjançant el token.
     */
    #[Route('/transfer/{token}/thumbnail', name: 'app_transfer_thumbnail', methods: ['GET'])]
    #[Route('/app/transfer/{token}/thumbnail', name: 'app_transfer_thumbnail_alt', methods: ['GET'])]
    public function thumbnail(
        string $token,
        \App\Service\Storage\SecureDocumentStorageService $storageService
    ): Response {
        $transfer = $this->itemTransferRepository->findOneBy(['token' => $token]);
        if (!$transfer) {
            throw $this->createNotFoundException('Traspàs no trobat.');
        }

        $photo = $transfer->getItem()->getMainPhoto();
        if (!$photo) {
            throw $this->createNotFoundException('Aquest bé no disposa de fotografia principal.');
        }

        try {
            $stream = $storageService->readDocumentStream($photo);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('No s\'ha pogut recuperar el document de custòdia.');
        }

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $photo->getMimeType());
        $response->headers->set('Cache-Control', 'private, max-age=1800');

        return $response;
    }

    /**
     * Pàgina pública de revisió i reclamació d'un traspàs notarial via testimoni segur (72h).
     */
    #[Route('/transfer/{token}', name: 'app_transfer_claim', methods: ['GET'])]
    #[Route('/app/transfer/{token}', name: 'app_transfer_claim_alt', methods: ['GET'])]
    public function claim(string $token): Response
    {
        $transfer = $this->itemTransferRepository->findOneBy(['token' => $token]);
        if (!$transfer) {
            return $this->render('transfer/expired.html.twig', [
                'reason' => 'invalid_token',
                'transfer' => null,
            ]);
        }

        // Comprovació de caducitat
        if ($transfer->isExpired() || $transfer->getStatus() === TransferStatus::EXPIRED) {
            if ($transfer->getStatus() === TransferStatus::PENDING) {
                $transfer->setStatus(TransferStatus::EXPIRED);
                $this->lifecycleService->expireTransfer($transfer->getItem());
                $this->entityManager->flush();
            }

            return $this->render('transfer/expired.html.twig', [
                'reason' => 'expired',
                'transfer' => $transfer,
            ]);
        }

        if ($transfer->getStatus() === TransferStatus::CANCELLED) {
            return $this->render('transfer/expired.html.twig', [
                'reason' => 'cancelled',
                'transfer' => $transfer,
            ]);
        }

        if ($transfer->getStatus() === TransferStatus::REJECTED) {
            return $this->render('transfer/expired.html.twig', [
                'reason' => 'rejected',
                'transfer' => $transfer,
            ]);
        }

        if ($transfer->getStatus() === TransferStatus::ACCEPTED) {
            return $this->render('transfer/expired.html.twig', [
                'reason' => 'already_accepted',
                'transfer' => $transfer,
            ]);
        }

        return $this->render('transfer/claim.html.twig', [
            'transfer' => $transfer,
            'item' => $transfer->getItem(),
            'sender' => $transfer->getSender(),
        ]);
    }

    /**
     * Accepta el traspàs i transmet la titularitat al nou usuari autenticat (ancorat a Polygon L2).
     */
    #[Route('/transfer/{token}/accept', name: 'app_transfer_accept', methods: ['POST'])]
    #[Route('/app/transfer/{token}/accept', name: 'app_transfer_accept_alt', methods: ['POST'])]
    public function accept(string $token, Request $request): Response
    {
        $transfer = $this->itemTransferRepository->findOneBy(['token' => $token]);
        if (!$transfer) {
            throw $this->createNotFoundException('Aquest traspàs no existeix.');
        }

        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            $this->addFlash('info', 'Cal que inicieu sessió o us registreu amb el vostre compte per acceptar la titularitat del bé.');
            $request->getSession()->set('_security.main.target_path', $this->generateUrl('app_transfer_claim', ['token' => $token]));
            return $this->redirectToRoute('app_login');
        }

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('accept_transfer_' . $transfer->getId(), $csrfToken)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');
            return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
        }

        if ($transfer->isExpired()) {
            $this->addFlash('error', 'Aquesta sol·licitud de traspàs ha caducat.');
            return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
        }

        // Verificació del PIN de seguretat si l'emissor en va definir un
        if ($transfer->getSecurityCode() !== null) {
            $providedCode = trim((string) $request->request->get('security_code', ''));
            if ($providedCode !== $transfer->getSecurityCode()) {
                $this->addFlash('error', 'El codi PIN de seguretat notarial no és correcte.');
                return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
            }
        }

        if (!$transfer->canBeAcceptedBy($currentUser)) {
            if (strtolower($currentUser->getEmail()) !== strtolower($transfer->getRecipientEmail())) {
                $this->addFlash('error', sprintf(
                    'Aquest traspàs està reservat a l\'adreça %s. Heu iniciat sessió com a %s.',
                    $transfer->getRecipientEmail(),
                    $currentUser->getEmail()
                ));
            } else {
                $this->addFlash('error', 'Aquest traspàs no pot ser acceptat amb l\'estat actual.');
            }
            return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
        }

        $item = $transfer->getItem();
        $previousOwner = $transfer->getSender();
        $transfer->accept($currentUser, $request->getClientIp());
        $this->lifecycleService->completeTransfer($item, $currentUser);
        $this->entityManager->flush();

        // Registre a la base d'auditoria forense (registrals_audit)
        $this->activityLogger?->log(
            user: $currentUser,
            action: UserActivityAction::TRANSFER_ACCEPTED,
            description: sprintf('Acceptació de traspàs i transmissió del bé %s', $item->getCui()),
            details: [
                'cui' => $item->getCui(),
                'item_name' => $item->getName(),
                'previous_owner' => $previousOwner->getEmail(),
                'new_owner' => $currentUser->getEmail(),
                'transferred_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]
        );

        $this->addFlash('success', sprintf(
            'Enhorabona! La titularitat de "%s" s\'ha transmès satisfactòriament al vostre compte i s\'ha registrat a la cadena de blocs Polygon L2.',
            $item->getName()
        ));

        return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
    }

    /**
     * Rebutja la sol·licitud de traspàs.
     */
    #[Route('/transfer/{token}/reject', name: 'app_transfer_reject', methods: ['POST'])]
    #[Route('/app/transfer/{token}/reject', name: 'app_transfer_reject_alt', methods: ['POST'])]
    public function reject(string $token, Request $request): Response
    {
        $transfer = $this->itemTransferRepository->findOneBy(['token' => $token]);
        if (!$transfer) {
            throw $this->createNotFoundException('Aquest traspàs no existeix.');
        }

        $csrfToken = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('reject_transfer_' . $transfer->getId(), $csrfToken)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');
            return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
        }

        if ($transfer->getStatus() !== TransferStatus::PENDING || $transfer->isExpired()) {
            return $this->redirectToRoute('app_transfer_claim', ['token' => $token]);
        }

        $reason = trim((string) $request->request->get('reason', 'Rebutjat pel receptor'));
        $transfer->reject($reason, $request->getClientIp());
        $this->lifecycleService->rejectTransfer($transfer->getItem());
        $this->entityManager->flush();

        // Registre a la base d'auditoria forense
        $currentUser = $this->getUser();
        if ($currentUser instanceof User) {
            $this->activityLogger?->log(
                user: $currentUser,
                action: UserActivityAction::TRANSFER_REJECTED,
                description: sprintf('Rebuig de la sol·licitud de traspàs del bé %s', $transfer->getItem()->getCui()),
                details: [
                    'cui' => $transfer->getItem()->getCui(),
                    'reason' => $reason,
                    'sender' => $transfer->getSender()->getEmail(),
                ]
            );
        } else {
            $this->activityLogger?->logAnonymous(
                identifier: $transfer->getRecipientEmail(),
                action: UserActivityAction::TRANSFER_REJECTED,
                description: sprintf('Rebuig anònim de la sol·licitud de traspàs del bé %s', $transfer->getItem()->getCui()),
                details: [
                    'cui' => $transfer->getItem()->getCui(),
                    'reason' => $reason,
                    'sender' => $transfer->getSender()->getEmail(),
                ]
            );
        }

        $this->addFlash('info', 'Heu rebutjat la sol·licitud de traspàs de titularitat.');

        return $this->render('transfer/expired.html.twig', [
            'reason' => 'just_rejected',
            'transfer' => $transfer,
        ]);
    }
}
