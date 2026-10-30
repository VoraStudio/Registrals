<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Infrastructure\Adapter\Signature\SignaturitClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SignaturitClientTest extends TestCase
{
    public function testFallbackModeGeneratesValidEidasDto(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $client = new SignaturitClient(
            httpClient: $httpClient,
            apiToken: '', // Token buit per forçar mode dev
            logger: new NullLogger()
        );

        $hash = hash('sha256', 'contingut-de-prova-pdf');
        $dto = $client->timestampHash($hash);

        $this->assertTrue($dto->isCertified);
        $this->assertStringStartsWith('eIDAS-SIG-', $dto->tokenId);
        $this->assertNotEmpty($dto->provider);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto->timestamp);
    }

    public function testApiCallParsesSuccessfulResponse(): void
    {
        $hash = hash('sha256', 'contingut-de-prova-pdf');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'id' => 'sig-token-real-12345',
            'created_at' => '2026-10-29T16:30:00Z',
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.signaturit.com/v3/timestamps.json',
                $this->callback(function (array $options) use ($hash) {
                    return $options['json']['hash'] === $hash
                        && $options['headers']['Authorization'] === 'Bearer real_api_token';
                })
            )
            ->willReturn($response);

        $client = new SignaturitClient(
            httpClient: $httpClient,
            apiToken: 'real_api_token',
            logger: new NullLogger()
        );

        $dto = $client->timestampHash($hash);

        $this->assertTrue($dto->isCertified);
        $this->assertSame('sig-token-real-12345', $dto->tokenId);
        $this->assertSame('2026-10-29 16:30:00', $dto->timestamp->format('Y-m-d H:i:s'));
    }
}
