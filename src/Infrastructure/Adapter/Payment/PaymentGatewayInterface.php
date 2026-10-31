<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Payment;

use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\Plan;
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

    public function createCustomerPortalSession(User $user, string $returnUrl): string;

    /**
     * @return array<string, mixed> Event payload
     */
    public function parseAndVerifyWebhook(string $rawPayload, string $signatureHeader): array;
}
