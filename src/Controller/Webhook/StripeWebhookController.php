<?php

declare(strict_types=1);

namespace App\Controller\Webhook;

use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Service\Payment\PaymentManager;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly PaymentManager $paymentManager,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/webhook/stripe', name: 'app_webhook_stripe', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $rawPayload = (string) $request->getContent();
        $signatureHeader = (string) $request->headers->get('stripe-signature', '');

        if ($rawPayload === '') {
            $this->logger->warning('Stripe webhook buit rebut.');
            return new JsonResponse(['error' => 'Payload buit'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = $this->paymentGateway->parseAndVerifyWebhook($rawPayload, $signatureHeader);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Signatura invàlida del webhook de Stripe: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Signatura invàlida: ' . $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('Error verificant webhook de Stripe: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Error de verificació'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $success = $this->paymentManager->processWebhook($event);
            return new JsonResponse(['received' => true, 'processed' => $success]);
        } catch (\Throwable $e) {
            $this->logger->error('Error processant el webhook de Stripe: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            // Retornem 500 només si volem que Stripe reintenti
            return new JsonResponse(['error' => 'Error intern processant esdeveniment'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
