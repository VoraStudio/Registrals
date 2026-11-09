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
use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Enum\PaymentType;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\ItemTransferRepository;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Service\Certificate\CertificateIssuerService;
use App\Service\Plan\PlanDowngradeService;
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
        private readonly LoggerInterface $logger,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?PlanDowngradeService $planDowngradeService = null
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
     * Cancel·la la subscripció d'un usuari: notifica la passarel·la i rebaixa el compte a Pla Free.
     */
    public function cancelUserSubscription(User $user, ?PaymentGatewayInterface $gateway = null): bool
    {
        if ($gateway !== null) {
            $gateway->cancelSubscription($user);
        }

        if ($this->planDowngradeService !== null) {
            $this->planDowngradeService->downgradeUserToFree($user);
        } else {
            $freePlan = $this->planRepository->findByCode('free');
            if ($freePlan !== null) {
                $user->setPlan($freePlan);
            }
            $user->setSubscriptionStatus('canceled');
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $this->activityLogger?->log(
            $user,
            UserActivityAction::SUBSCRIPTION_CANCELLED,
            'Subscripció cancel·lada per petició directa de l\'usuari. Retorn al Pla Free.',
            [
                'subscription_id' => (string) ($user->getStripeSubscriptionId() ?? ''),
                'customer_id' => (string) ($user->getStripeCustomerId() ?? ''),
            ]
        );

        return true;
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
        $amountTotal = (int) ($session['amount_total'] ?? 0);
        $amountFormatted = number_format($amountTotal / 100, 2, ',', '.') . ' €';

        if ($typeStr === 'subscription') {
            $planCode = (string) ($metadata['plan_code'] ?? 'plus');
            $plan = $this->planRepository->findByCode($planCode);
            if ($plan !== null) {
                $user->setPlan($plan);
                $user->setPlanStartedAt(new \DateTimeImmutable());
                $user->setPlanExpiresAt(new \DateTimeImmutable('+1 year'));
                $user->setPeriodStartedAt(new \DateTimeImmutable());
                $user->setPeriodItemsRegistered(0);
                $user->setSubscriptionStatus('active');
                if (isset($session['subscription']) && \is_string($session['subscription'])) {
                    $user->setStripeSubscriptionId($session['subscription']);
                }
                $this->entityManager->persist($user);
                $this->planDowngradeService?->unfreezeUserItems($user);

                $this->activityLogger?->log(
                    $user,
                    UserActivityAction::SUBSCRIPTION_STARTED,
                    sprintf('Subscripció al Pla %s activada via Stripe (%s)', strtoupper($planCode), $amountFormatted),
                    [
                        'session_id' => $sessionId,
                        'event_id' => $eventId,
                        'plan_code' => $planCode,
                        'amount' => $amountTotal,
                    ]
                );
            }
        } elseif ($typeStr === 'certificate') {
            $itemId = (int) ($metadata['item_id'] ?? 0);
            $item = $itemId > 0 ? $this->itemRepository->find($itemId) : null;
            if ($item !== null) {
                // Emet el certificat oficial de pagament (compra directa autoritzada)
                $this->certificateIssuer->issueCertificate($item, $user, CertificateMode::OFFICIAL, true);

                $this->activityLogger?->log(
                    $user,
                    UserActivityAction::PAYMENT_COMPLETED,
                    sprintf('Pagament de certificat oficial completat per al bé %s (%s)', $item->getCui(), $amountFormatted),
                    [
                        'session_id' => $sessionId,
                        'event_id' => $eventId,
                        'cui' => $item->getCui(),
                        'item_id' => $itemId,
                        'amount' => $amountTotal,
                    ]
                );
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

                $this->activityLogger?->log(
                    $user,
                    UserActivityAction::PAYMENT_COMPLETED,
                    sprintf('Pagament de taxa de traspàs C2C completat (%s)', $amountFormatted),
                    [
                        'session_id' => $sessionId,
                        'event_id' => $eventId,
                        'transfer_id' => $transferId,
                        'amount' => $amountTotal,
                    ]
                );
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
        $user->setPeriodStartedAt(new \DateTimeImmutable());
        $user->setPeriodItemsRegistered(0);
        $user->setSubscriptionStatus('active');

        $amountPaid = (int) ($invoice['amount_paid'] ?? 0);
        $amountFormatted = number_format($amountPaid / 100, 2, ',', '.') . ' €';
        $transaction = new PaymentTransaction($user, PaymentType::SUBSCRIPTION, $amountPaid);
        $transaction->setStatus(PaymentStatus::PAID);
        $transaction->setStripeEventId($eventId);
        $transaction->setStripeSubscriptionId($subscriptionId);
        $transaction->setStripeInvoiceId((string) ($invoice['id'] ?? ''));

        $this->entityManager->persist($user);
        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->activityLogger?->log(
            $user,
            UserActivityAction::PAYMENT_COMPLETED,
            sprintf('Renovació periòdica de subscripció cobrada amb èxit (%s)', $amountFormatted),
            [
                'invoice_id' => (string) ($invoice['id'] ?? ''),
                'subscription_id' => $subscriptionId,
                'event_id' => $eventId,
                'amount' => $amountPaid,
            ]
        );

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

            $this->activityLogger?->log(
                $user,
                UserActivityAction::PAYMENT_FAILED,
                sprintf('Cobrament de factura de subscripció fallit via Stripe (%s)', (string) ($invoice['id'] ?? '')),
                [
                    'invoice_id' => (string) ($invoice['id'] ?? ''),
                    'customer_id' => $customerId,
                    'event_id' => (string) ($event['id'] ?? ''),
                ]
            );
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
            if ($this->planDowngradeService !== null) {
                $this->planDowngradeService->downgradeUserToFree($user);
            } else {
                $freePlan = $this->planRepository->findByCode('free');
                if ($freePlan !== null) {
                    $user->setPlan($freePlan);
                }
                $user->setSubscriptionStatus('canceled');
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }

            $this->activityLogger?->log(
                $user,
                UserActivityAction::SUBSCRIPTION_CANCELLED,
                'Subscripció Stripe cancel·lada. Retorn automàtic al Pla Free.',
                [
                    'subscription_id' => (string) ($subscription['id'] ?? ''),
                    'customer_id' => $customerId,
                    'event_id' => (string) ($event['id'] ?? ''),
                ]
            );
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

                $refundUser = $transaction->getUser();
                if ($refundUser !== null) {
                    $amountRefunded = (int) ($charge['amount_refunded'] ?? $transaction->getAmount());
                    $amountRefundedFormatted = number_format($amountRefunded / 100, 2, ',', '.') . ' €';
                    $this->activityLogger?->log(
                        $refundUser,
                        UserActivityAction::REFUND_PROCESSED,
                        sprintf('Reemborsament de %s executat via Stripe.', $amountRefundedFormatted),
                        [
                            'payment_intent_id' => $paymentIntent,
                            'transaction_id' => $transaction->getId(),
                            'charge_id' => (string) ($charge['id'] ?? ''),
                        ]
                    );
                }
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
