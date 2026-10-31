<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\PaymentTransaction;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\ItemTransferRepository;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\Certificate\CertificateIssuerService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PaymentManager
{
    public function __construct(
        private readonly PaymentTransactionRepository $transactionRepository,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly ItemRepository $itemRepository,
        private readonly ItemTransferRepository $transferRepository,
        private readonly CertificateIssuerService $certificateIssuer,
        private readonly CertificateRepository $certificateRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function createPendingTransaction(
        User $user,
        PaymentType $type,
        int $amountCents,
        string $sessionId,
        array $metadata = []
    ): PaymentTransaction {
        $transaction = new PaymentTransaction($user, $type, $amountCents);
        $transaction->setStripeSessionId($sessionId);
        $transaction->setStatus(PaymentStatus::PENDING);
        $transaction->setMetadata($metadata);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    /**
     * @param array<string, mixed> $event
     */
    public function processWebhook(array $event): bool
    {
        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? '');

        if ($eventId === '') {
            return false;
        }

        // 1. Idempotència estricta: comprovar si l'esdeveniment ja s'ha processat
        $existing = $this->transactionRepository->findByStripeEventId($eventId);
        if ($existing !== null) {
            $this->logger->info(sprintf('Stripe Webhook ja processat anteriorment: %s', $eventId));
            return true;
        }

        $this->logger->info(sprintf('Processant Stripe Webhook: %s (%s)', $eventType, $eventId));

        return match ($eventType) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'charge.refunded' => $this->handleChargeRefunded($event),
            default => $this->handleUnprocessedEvent($event),
        };
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleCheckoutSessionCompleted(array $event): bool
    {
        $session = $event['data']['object'] ?? [];
        $sessionId = (string) ($session['id'] ?? '');
        $metadata = $session['metadata'] ?? [];
        $typeStr = (string) ($metadata['type'] ?? 'subscription');
        $userId = (int) ($metadata['user_id'] ?? $session['client_reference_id'] ?? 0);
        $eventId = (string) ($event['id'] ?? '');

        $transaction = $this->transactionRepository->findByStripeSessionId($sessionId);
        $user = $userId > 0 ? $this->userRepository->find($userId) : null;

        if ($transaction === null && $user !== null) {
            $amountTotal = (int) ($session['amount_total'] ?? 0);
            $paymentType = PaymentType::tryFrom($typeStr) ?? PaymentType::SUBSCRIPTION;
            $transaction = new PaymentTransaction($user, $paymentType, $amountTotal);
            $transaction->setStripeSessionId($sessionId);
        }

        if ($transaction !== null) {
            $transaction->setStatus(PaymentStatus::PAID);
            $transaction->setStripeEventId($eventId);
            if (isset($session['payment_intent']) && \is_string($session['payment_intent'])) {
                $transaction->setStripePaymentIntentId($session['payment_intent']);
            }
            if (isset($session['subscription']) && \is_string($session['subscription'])) {
                $transaction->setStripeSubscriptionId($session['subscription']);
            }
            $this->entityManager->persist($transaction);
        }

        if ($user === null) {
            $this->entityManager->flush();
            return true;
        }

        // Acció de negoci segons el tipus de transacció
        if ($typeStr === 'subscription') {
            $planCode = (string) ($metadata['plan_code'] ?? 'plus');
            $plan = $this->planRepository->findByCode($planCode);
            if ($plan !== null) {
                $user->setPlan($plan);
                $user->setPlanStartedAt(new \DateTimeImmutable());
                $user->setPlanExpiresAt(new \DateTimeImmutable('+1 year'));
                $user->setSubscriptionStatus('active');
                if (isset($session['subscription']) && \is_string($session['subscription'])) {
                    $user->setStripeSubscriptionId($session['subscription']);
                }
                $this->entityManager->persist($user);
            }
        } elseif ($typeStr === 'certificate') {
            $itemId = (int) ($metadata['item_id'] ?? 0);
            $item = $itemId > 0 ? $this->itemRepository->find($itemId) : null;
            if ($item !== null) {
                // Emet el certificat oficial de pagament (compra directa autoritzada)
                $this->certificateIssuer->issueCertificate($item, $user, CertificateMode::OFFICIAL, true);
            }
        } elseif ($typeStr === 'transfer') {
            $transferId = (int) ($metadata['transfer_id'] ?? 0);
            $transfer = $transferId > 0 ? $this->transferRepository->find($transferId) : null;
            if ($transfer !== null) {
                $transferMeta = $transfer->getMetadata() ?? [];
                $transferMeta['payment_status'] = 'paid';
                $transferMeta['stripe_session_id'] = $sessionId;
                $transfer->setMetadata($transferMeta);
                $this->entityManager->persist($transfer);
            }
        }

        $this->entityManager->flush();
        return true;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleInvoicePaymentSucceeded(array $event): bool
    {
        $invoice = $event['data']['object'] ?? [];
        $subscriptionId = (string) ($invoice['subscription'] ?? '');
        $customerId = (string) ($invoice['customer'] ?? '');
        $eventId = (string) ($event['id'] ?? '');

        if ($customerId === '') {
            return false;
        }

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if ($user === null) {
            return false;
        }

        // Allarga el pla 1 any des d'ara o des de la caducitat anterior
        $currentExpiry = $user->getPlanExpiresAt() ?? new \DateTimeImmutable();
        $baseDate = $currentExpiry > new \DateTimeImmutable() ? $currentExpiry : new \DateTimeImmutable();
        $user->setPlanExpiresAt($baseDate->modify('+1 year'));
        $user->setSubscriptionStatus('active');

        $amountPaid = (int) ($invoice['amount_paid'] ?? 0);
        $transaction = new PaymentTransaction($user, PaymentType::SUBSCRIPTION, $amountPaid);
        $transaction->setStatus(PaymentStatus::PAID);
        $transaction->setStripeEventId($eventId);
        $transaction->setStripeSubscriptionId($subscriptionId);
        $transaction->setStripeInvoiceId((string) ($invoice['id'] ?? ''));

        $this->entityManager->persist($user);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleInvoicePaymentFailed(array $event): bool
    {
        $invoice = $event['data']['object'] ?? [];
        $customerId = (string) ($invoice['customer'] ?? '');

        if ($customerId === '') {
            return false;
        }

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if ($user !== null) {
            $user->setSubscriptionStatus('past_due');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return true;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleSubscriptionDeleted(array $event): bool
    {
        $subscription = $event['data']['object'] ?? [];
        $customerId = (string) ($subscription['customer'] ?? '');

        if ($customerId === '') {
            return false;
        }

        $user = $this->userRepository->findOneBy(['stripeCustomerId' => $customerId]);
        if ($user !== null) {
            $freePlan = $this->planRepository->findByCode('free');
            if ($freePlan !== null) {
                $user->setPlan($freePlan);
            }
            $user->setSubscriptionStatus('canceled');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        return true;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleChargeRefunded(array $event): bool
    {
        $charge = $event['data']['object'] ?? [];
        $paymentIntent = (string) ($charge['payment_intent'] ?? '');

        if ($paymentIntent !== '') {
            $transaction = $this->transactionRepository->findByStripePaymentIntentId($paymentIntent);
            if ($transaction !== null) {
                $transaction->setStatus(PaymentStatus::REFUNDED);
                $this->entityManager->persist($transaction);
                $this->entityManager->flush();
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function handleUnprocessedEvent(array $event): bool
    {
        $this->logger->debug(sprintf('Esdeveniment Stripe registrat sense acció: %s', $event['type'] ?? 'unknown'));
        return true;
    }
}
