<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\BlockchainReceiptDto;
use App\Infrastructure\Adapter\Blockchain\PolygonAnchorClient;
use App\Service\ItemFingerprintCalculator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ItemFingerprintCalculatorTest extends TestCase
{
    public function testDeterministicHashCalculation(): void
    {
        $calculator = new ItemFingerprintCalculator();

        $cui = 'REG-2026-A1B2C3';
        $ownerUuid = '018f4a1b-7c2a-7100-a42e-123456789abc';
        $docHashes1 = [
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        ];
        $docHashes2 = [
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
        ];
        $timestamp = '2026-10-29T12:00:00+00:00';

        $hash1 = $calculator->calculateRaw($cui, $ownerUuid, $docHashes1, $timestamp);
        $hash2 = $calculator->calculateRaw($cui, $ownerUuid, $docHashes2, $timestamp);

        // Determinisme absolut malgrat que l'ordre inicial d'arribada dels documents sigui diferent
        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash1);
    }

    public function testPolygonAnchorClientReturnsValidReceipt(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $response->method('toArray')
            ->willReturn(['result' => '0x32a100']); // 3318016 en decimal

        $httpClient->method('request')
            ->willReturn($response);

        $client = new PolygonAnchorClient(
            httpClient: $httpClient,
            rpcUrl: 'https://polygon-rpc.com',
            contractAddress: '0x1234567890123456789012345678901234567890',
            walletPrivateKey: '',
            network: 'polygon-pos'
        );

        $cui = 'REG-2026-TEST';
        $sha256 = hash('sha256', 'sample payload');

        $receipt = $client->anchorHash($cui, $sha256);

        $this->assertInstanceOf(BlockchainReceiptDto::class, $receipt);
        $this->assertTrue($receipt->isSuccess);
        $this->assertSame($sha256, $receipt->anchoredHash);
        $this->assertSame(3318016, $receipt->blockNumber);
        $this->assertSame('polygon-pos', $receipt->network);
        $this->assertStringStartsWith('0x', $receipt->txHash);
        $this->assertSame(66, strlen($receipt->txHash));
    }
}
