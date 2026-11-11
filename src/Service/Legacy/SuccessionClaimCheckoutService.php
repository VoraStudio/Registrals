<?php

declare(strict_types=1);

namespace App\Service\Legacy;

use App\Entity\SuccessionClaim;
use App\Enum\PaymentType;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Service\Payment\PaymentManager;

/**
 * Coordina l'obertura del pagament Stripe de la taxa successòria tant des del QR públic
 * com des del portal autenticat de la Persona de Confiança.
 */
class SuccessionClaimCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly PaymentManager $paymentManager,
    ) {
    }

    /**
     * Crea la sessió de Checkout Stripe, registra la transacció pendent i liquida en línia
     * automàticament si l'adaptador opera en mode mock de proves.
     */
    public function startOpeningFeeCheckout(SuccessionClaim $claim, string $successUrl, string $cancelUrl): string
    {
        $amountCents = $claim->getDeceasedOwner()->getPlan()?->getLegacyClaimOpeningFeeCents() ?? 1900;
        $checkout = $this->paymentGateway->createSuccessionClaimCheckout($claim, $amountCents, $successUrl, $cancelUrl);
        $payerUser = $claim->getClaimantUser() ?? $claim->getDeceasedOwner();

        $this->paymentManager->createPendingTransaction(
            $payerUser,
            PaymentType::SUCCESSION_CLAIM,
            $amountCents,
            $checkout['sessionId'],
            [
                'type' => 'succession_claim',
                'claim_id' => $claim->getId(),
                'claim_uuid' => $claim->getUuid()->toRfc4122(),
                'claimant_email' => $claim->getClaimantEmail(),
            ]
        );

        if (str_contains($checkout['url'], 'mock=1')) {
            $this->paymentManager->processWebhook([
                'id' => 'evt_mock_claim_' . bin2hex(random_bytes(8)),
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => [
                        'id' => $checkout['sessionId'],
                        'client_reference_id' => $claim->getUuid()->toRfc4122(),
                        'amount_total' => $amountCents,
                        'payment_intent' => 'pi_mock_' . bin2hex(random_bytes(8)),
                        'metadata' => [
                            'type' => 'succession_claim',
                            'user_id' => (string) $payerUser->getId(),
                            'claim_id' => (string) $claim->getId(),
                        ],
                    ],
                ],
            ]);
        }

        return $checkout['url'];
    }
}

