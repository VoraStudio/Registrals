<?php

declare(strict_types=1);

namespace App\Controller\Webhook;

use App\Infrastructure\Adapter\Identity\IdentityVerificationGatewayInterface;
use App\Service\Identity\KycVerificationService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NamirialWebhookController extends AbstractController
{
    public function __construct(
        private readonly IdentityVerificationGatewayInterface $identityGateway,
        private readonly KycVerificationService $kycService,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/webhook/namirial/onboarding', name: 'app_webhook_namirial_onboarding', methods: ['POST'])]
    public function handle(Request $request): Response
    {
        $providedSecret = $this->extractSecretHeader($request);
        if (!$this->identityGateway->verifyWebhookSecret($providedSecret)) {
            $this->logger->warning('Intent no autoritzat al webhook de Namirial OnBoarding.', [
                'ip' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $rawPayload = trim((string) $request->getContent());
        if ($rawPayload === '') {
            return new JsonResponse(['error' => 'Payload buit'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($rawPayload, true);
        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'JSON invàlid'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $processed = $this->kycService->handleWebhookPayload($payload, $request->getClientIp());

            return new JsonResponse(['received' => true, 'processed' => $processed]);
        } catch (\Throwable $e) {
            $this->logger->error('Error processant webhook de Namirial OnBoarding: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return new JsonResponse(['error' => 'Error intern'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function extractSecretHeader(Request $request): ?string
    {
        foreach (['X-Registrals-Webhook-Secret', 'X-Webhook-Secret'] as $headerName) {
            $val = $request->headers->get($headerName);
            if ($val !== null && $val !== '') {
                return $val;
            }
        }

        $auth = (string) $request->headers->get('Authorization', '');
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return $auth !== '' ? $auth : null;
    }
}

