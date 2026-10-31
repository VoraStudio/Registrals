<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Webhook\StripeWebhookController;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Service\Payment\PaymentManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookControllerTest extends TestCase
{
    public function testHandleEmptyPayloadReturnsBadRequest(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $manager = $this->createMock(PaymentManager::class);
        $controller = new StripeWebhookController($gateway, $manager, new NullLogger());

        $request = new Request([], [], [], [], [], [], '');
        $response = $controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('Payload buit', $data['error']);
    }

    public function testHandleInvalidSignatureReturnsBadRequest(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('parseAndVerifyWebhook')
            ->willThrowException(new SignatureVerificationException('Signatura invàlida'));

        $manager = $this->createMock(PaymentManager::class);
        $controller = new StripeWebhookController($gateway, $manager, new NullLogger());

        $request = new Request([], [], [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=123,v1=bad_sig'], '{"type":"test"}');
        $response = $controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testHandleValidEventProcessesSuccessfully(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $eventData = [
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_123']],
        ];
        $gateway->method('parseAndVerifyWebhook')->willReturn($eventData);

        $manager = $this->createMock(PaymentManager::class);
        $manager->expects($this->once())
            ->method('processWebhook')
            ->with($eventData)
            ->willReturn(true);

        $controller = new StripeWebhookController($gateway, $manager, new NullLogger());

        $request = new Request([], [], [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=123,v1=valid_sig'], json_encode($eventData, JSON_THROW_ON_ERROR));
        $response = $controller->handle($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['received']);
        $this->assertTrue($data['processed']);
    }
}
