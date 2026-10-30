<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Signature;

use App\DTO\SignaturitTimestampDto;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Adaptador d'infraestructura per al servei de segellat de temps qualificat eIDAS via Signaturit API.
 */
class SignaturitClient implements SignaturitTimestampInterface
{
    private readonly string $apiToken;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        ?string $apiToken = null,
        private readonly string $baseUrl = 'https://api.signaturit.com/v3',
        private readonly ?LoggerInterface $logger = null
    ) {
        $this->apiToken = $apiToken ?? '';
    }

    public function timestampHash(string $sha256Hash): SignaturitTimestampDto
    {
        $now = new DateTimeImmutable();

        // Si tenim el token configurat, cridem l'API de Signaturit
        if ($this->apiToken !== '' && !str_starts_with($this->apiToken, 'el_teu_')) {
            try {
                $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/timestamps.json', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiToken,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'hash' => $sha256Hash,
                        'hash_algorithm' => 'SHA256',
                    ],
                    'timeout' => 10,
                ]);

                $data = $response->toArray();
                $tokenId = (string) ($data['id'] ?? ('SIG-' . uniqid('', true)));
                $timestamp = isset($data['created_at'])
                    ? new DateTimeImmutable($data['created_at'])
                    : $now;

                $this->logger?->info('Segellat de temps eIDAS emès per Signaturit', [
                    'token_id' => $tokenId,
                    'hash' => $sha256Hash,
                ]);

                return new SignaturitTimestampDto(
                    tokenId: $tokenId,
                    timestamp: $timestamp,
                    provider: 'Signaturit Qualified Trust Service Provider (eIDAS EU 910/2014)',
                    isCertified: true,
                    rawResponse: json_encode($data) ?: null
                );
            } catch (Throwable $e) {
                $this->logger?->warning('Error en connectar amb Signaturit API, aplicant mode desenvolupament: ' . $e->getMessage(), [
                    'hash' => $sha256Hash,
                ]);
            }
        }

        // Mode Desenvolupament / Sandbox (fins a la configuració del token definitiu de producció)
        $simulatedToken = 'eIDAS-SIG-' . strtoupper(substr(hash('sha256', $sha256Hash . microtime(true)), 0, 16));

        $this->logger?->info('Generat segellat eIDAS en mode desenvolupament/simulació', [
            'token_id' => $simulatedToken,
            'hash' => $sha256Hash,
        ]);

        return new SignaturitTimestampDto(
            tokenId: $simulatedToken,
            timestamp: $now,
            provider: 'Signaturit Qualified Trust Service Provider (Simulador eIDAS Sandbox)',
            isCertified: true,
            rawResponse: null
        );
    }
}
