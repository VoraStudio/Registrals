<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Payment;

use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\Plan;
use App\Entity\SuccessionClaim;
use App\Entity\User;

/**
 * Port de domini (Arquitectura Hexagonal) per a la passarel·la de pagament.
 */
interface PaymentGatewayInterface
{
    /**
     * @return array{sessionId: string, url: string}
     */
    public function createSubscriptionCheckout(User $user, Plan $plan, string $successUrl, string $cancelUrl): array;

    /**
     * @return array{sessionId: string, url: string}
     */
    public function createCertificateCheckout(User $user, Item $item, int $amountCents, string $successUrl, string $cancelUrl): array;

    /**
     * @return array{sessionId: string, url: string}
     */
    public function createTransferCheckout(User $user, ItemTransfer $transfer, int $amountCents, string $successUrl, string $cancelUrl): array;

    /**
     * @return array{sessionId: string, url: string}
     */
    public function createSuccessionClaimCheckout(SuccessionClaim $claim, int $amountCents, string $successUrl, string $cancelUrl): array;

    /**
     * @return array{sessionId: string, url: string}
     */
    public function createKycCheckout(User $user, int $amountCents, string $successUrl, string $cancelUrl): array;

    public function createCustomerPortalSession(User $user, string $returnUrl): string;

    /**
     * Cancel·la la subscripció vigent a Stripe (immediatament o a final de període).
     */
    public function cancelSubscription(User $user): bool;

    /**
     * Recupera els detalls d'una Checkout Session directament de l'API de Stripe per a sincronització activa.
     *
     * @return array<string, mixed>|null
     */
    public function fetchCheckoutSession(string $sessionId): ?array;

    /**
     * @return array<string, mixed> Event payload
     */
    public function parseAndVerifyWebhook(string $rawPayload, string $signatureHeader): array;
}
