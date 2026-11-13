<?php

declare(strict_types=1);

namespace App\Tests\Controller\Webhook;

use App\Controller\Webhook\NamirialWebhookController;
use App\Infrastructure\Adapter\Identity\IdentityVerificationGatewayInterface;
use App\Service\Identity\KycVerificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NamirialWebhookControllerTest extends TestCase
{
    public function testRejectsUnauthorizedWebhookSecret(): void
    {
        $gateway = $this->createMock(IdentityVerificationGatewayInterface::class);
        $gateway->method('verifyWebhookSecret')->willReturn(false);

        $service = $this->createMock(KycVerificationService::class);
        $service->expects($this->never())->method('handleWebhookPayload');

        $controller = new NamirialWebhookController($gateway, $service, new NullLogger());
        $request = new Request([], [], [], [], [], ['HTTP_X_REGISTRALS_WEBHOOK_SECRET' => 'wrong'], '{"requestId":"r1"}');

        $response = $controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testProcessesAuthorizedWebhookPayload(): void
    {
        $gateway = $this->createMock(IdentityVerificationGatewayInterface::class);
        $gateway->method('verifyWebhookSecret')->with('good-secret')->willReturn(true);

        $service = $this->createMock(KycVerificationService::class);
        $service->expects($this->once())
            ->method('handleWebhookPayload')
            ->with(['requestId' => 'req-123', 'eventType' => 'REQUEST_FINALIZED'], $this->anything())
            ->willReturn(true);

        $controller = new NamirialWebhookController($gateway, $service, new NullLogger());
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            ['HTTP_X_REGISTRALS_WEBHOOK_SECRET' => 'good-secret'],
            json_encode(['requestId' => 'req-123', 'eventType' => 'REQUEST_FINALIZED'], JSON_THROW_ON_ERROR)
        );

        $response = $controller->handle($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['received']);
        $this->assertTrue($data['processed']);
    }
}

