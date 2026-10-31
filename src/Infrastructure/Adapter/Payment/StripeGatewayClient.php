<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Payment;

use App\Entity\Item;
use App\Entity\ItemTransfer;
use App\Entity\Plan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeGatewayClient implements PaymentGatewayInterface
{
    private ?StripeClient $stripe = null;

    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly string $pricePlusId,
        private readonly string $priceUnlimitedId,
        private readonly EntityManagerInterface $entityManager
    ) {
        if (!empty($this->secretKey) && !str_contains($this->secretKey, 'mock')) {
            $this->stripe = new StripeClient($this->secretKey);
        }
    }

    private function getClient(): StripeClient
    {
        if ($this->stripe === null) {
            $this->stripe = new StripeClient($this->secretKey);
        }

        return $this->stripe;
    }

    private function getOrCreateCustomer(User $user): string
    {
        if ($user->getStripeCustomerId() !== null && !empty($user->getStripeCustomerId())) {
            return $user->getStripeCustomerId();
        }

        // Si és clau de test fictícia i no podem connectar amb Stripe real, retornem ID simulat
        if (str_contains($this->secretKey, 'mock')) {
            $mockCustomerId = 'cus_mock_' . bin2hex(random_bytes(8));
            $user->setStripeCustomerId($mockCustomerId);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            return $mockCustomerId;
        }

        $customer = $this->getClient()->customers->create([
            'email' => $user->getEmail(),
            'name' => trim($user->getFirstName() . ' ' . $user->getLastName()),
            'metadata' => [
                'user_id' => (string) $user->getId(),
                'user_uuid' => $user->getUuid()->toRfc4122(),
            ],
        ]);

        $user->setStripeCustomerId($customer->id);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $customer->id;
    }

    public function createSubscriptionCheckout(User $user, Plan $plan, string $successUrl, string $cancelUrl): array
    {
        $customerId = $this->getOrCreateCustomer($user);

        $priceId = match (strtolower($plan->getCode())) {
            'plus' => $this->pricePlusId,
            'unlimited' => $this->priceUnlimitedId,
            default => throw new \InvalidArgumentException(sprintf('Pla no contractable via Stripe: %s', $plan->getCode())),
        };

        if (str_contains($this->secretKey, 'mock')) {
            $sessionId = 'cs_mock_sub_' . bin2hex(random_bytes(10));
            $separator = str_contains($successUrl, '?') ? '&' : '?';
            return [
                'sessionId' => $sessionId,
                'url' => str_replace(['{CHECKOUT_SESSION_ID}', '%7BCHECKOUT_SESSION_ID%7D'], $sessionId, $successUrl) . $separator . 'mock=1',
            ];
        }

        $session = $this->getClient()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $user->getId(),
            'metadata' => [
                'type' => 'subscription',
                'user_id' => (string) $user->getId(),
                'plan_code' => $plan->getCode(),
            ],
        ]);

        return [
            'sessionId' => $session->id,
            'url' => $session->url ?? $successUrl,
        ];
    }

    public function createCertificateCheckout(User $user, Item $item, int $amountCents, string $successUrl, string $cancelUrl): array
    {
        $customerId = $this->getOrCreateCustomer($user);

        if (str_contains($this->secretKey, 'mock')) {
            $sessionId = 'cs_mock_cert_' . bin2hex(random_bytes(10));
            $separator = str_contains($successUrl, '?') ? '&' : '?';
            return [
                'sessionId' => $sessionId,
                'url' => str_replace(['{CHECKOUT_SESSION_ID}', '%7BCHECKOUT_SESSION_ID%7D'], $sessionId, $successUrl) . $separator . 'mock=1',
            ];
        }

        $session = $this->getClient()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => 'Certificat Oficial Registrals — ' . $item->getName(),
                            'description' => 'Segellat legal eIDAS (RFC 3161) i notarització Polygon Blockchain per al bé CUI ' . $item->getCui(),
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $user->getId(),
            'metadata' => [
                'type' => 'certificate',
                'user_id' => (string) $user->getId(),
                'item_id' => (string) $item->getId(),
                'item_cui' => $item->getCui(),
            ],
        ]);

        return [
            'sessionId' => $session->id,
            'url' => $session->url ?? $successUrl,
        ];
    }

    public function createTransferCheckout(User $user, ItemTransfer $transfer, int $amountCents, string $successUrl, string $cancelUrl): array
    {
        $customerId = $this->getOrCreateCustomer($user);

        if (str_contains($this->secretKey, 'mock')) {
            $sessionId = 'cs_mock_txf_' . bin2hex(random_bytes(10));
            $separator = str_contains($successUrl, '?') ? '&' : '?';
            return [
                'sessionId' => $sessionId,
                'url' => str_replace(['{CHECKOUT_SESSION_ID}', '%7BCHECKOUT_SESSION_ID%7D'], $sessionId, $successUrl) . $separator . 'mock=1',
            ];
        }

        $session = $this->getClient()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $customerId,
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => 'Taxa de Traspàs Notarial C2C — ' . $transfer->getItem()->getName(),
                            'description' => 'Canvi de titularitat digital registral i emissió de títol per al nou adquirent',
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $user->getId(),
            'metadata' => [
                'type' => 'transfer',
                'user_id' => (string) $user->getId(),
                'transfer_id' => (string) $transfer->getId(),
                'transfer_token' => $transfer->getToken(),
                'item_cui' => $transfer->getItem()->getCui(),
            ],
        ]);

        return [
            'sessionId' => $session->id,
            'url' => $session->url ?? $successUrl,
        ];
    }

    public function createCustomerPortalSession(User $user, string $returnUrl): string
    {
        $customerId = $this->getOrCreateCustomer($user);

        if (str_contains($this->secretKey, 'mock')) {
            return $returnUrl . (str_contains($returnUrl, '?') ? '&' : '?') . 'portal=mock';
        }

        $session = $this->getClient()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }

    public function parseAndVerifyWebhook(string $rawPayload, string $signatureHeader): array
    {
        if (str_contains($this->webhookSecret, 'mock')) {
            $data = json_decode($rawPayload, true);
            if (!\is_array($data) || !isset($data['type'])) {
                throw new SignatureVerificationException('Payload JSON invàlid en mode mock.');
            }
            return $data;
        }

        $event = Webhook::constructEvent($rawPayload, $signatureHeader, $this->webhookSecret, 300);

        return $event->toArray();
    }
}
