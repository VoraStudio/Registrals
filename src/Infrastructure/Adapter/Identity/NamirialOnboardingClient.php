<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Identity;

use App\DTO\KycSessionDto;
use App\DTO\KycVerdictDto;
use App\Entity\User;
use App\Enum\KycStatus;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Adaptador HTTP per a Namirial OnBoarding API v2 (verificació documental, liveness facial i comparativa nominal).
 */
class NamirialOnboardingClient implements IdentityVerificationGatewayInterface
{
    private const DEFAULT_TIMEOUT_SECONDS = 10.0;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly string $requestTypeId;
    private readonly string $webhookSecret;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        ?string $baseUrl = 'https://test-eu-ie1-api.namirialonboarding.com/api/v2',
        ?string $apiKey = null,
        ?string $requestTypeId = null,
        ?string $webhookSecret = null,
        private readonly ?LoggerInterface $logger = null
    ) {
        $this->baseUrl = rtrim($baseUrl ?? 'https://test-eu-ie1-api.namirialonboarding.com/api/v2', '/');
        $this->apiKey = trim($apiKey ?? '');
        $this->requestTypeId = trim($requestTypeId ?? '');
        $this->webhookSecret = trim($webhookSecret ?? '');
    }

    public function isLiveConfigured(): bool
    {
        return $this->apiKey !== ''
            && $this->requestTypeId !== ''
            && !str_starts_with($this->apiKey, 'el_teu_');
    }

    public function verifyWebhookSecret(?string $providedSecret): bool
    {
        if ($this->webhookSecret === '' || $providedSecret === null || $providedSecret === '') {
            return false;
        }

        return hash_equals($this->webhookSecret, trim($providedSecret));
    }

    public function startVerificationSession(User $user, string $referenceId, string $wizardUrlBack): KycSessionDto
    {
        $firstName = trim($user->getFirstName());
        $lastName = trim($user->getLastName());

        if ($firstName === '' || $lastName === '') {
            throw new InvalidArgumentException(
                'Cal completar el nom i cognoms al perfil abans d\'iniciar la verificació d\'identitat.'
            );
        }

        if (!$this->isLiveConfigured()) {
            $simulatedRequestId = 'sim-' . Uuid::v4()->toRfc4122();
            $simulatedWizardUrl = $this->buildSimulatedWizardUrl($wizardUrlBack, $simulatedRequestId, $referenceId);

            $this->logger?->info('Sessió KYC iniciada en mode simulació local (sense API key configurada)', [
                'request_id' => $simulatedRequestId,
                'reference_id' => $referenceId,
                'user_id' => $user->getId(),
            ]);

            return new KycSessionDto(
                requestId: $simulatedRequestId,
                referenceId: $referenceId,
                wizardUrl: $simulatedWizardUrl,
                isSimulated: true
            );
        }

        $parameters = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $user->getEmail(),
        ];

        $phone = trim((string) ($user->getPhone() ?? ''));
        if ($phone !== '') {
            $parameters['phoneNumber'] = $phone;
        }

        $payload = [
            'requestTypeId' => $this->requestTypeId,
            'referenceId' => $referenceId,
            'parameters' => $parameters,
            'settings' => [
                'wizardUrlBack' => $wizardUrlBack,
            ],
        ];

        $created = $this->requestJson('POST', '/requests', $payload);
        $requestId = (string) ($created['id'] ?? '');
        if ($requestId === '') {
            throw new RuntimeException('Namirial OnBoarding no ha retornat un identificador de sol·licitud vàlid.');
        }

        $wizardUrl = $this->extractWizardUrl($created);
        if ($wizardUrl === null) {
            $linksResponse = $this->requestJson('POST', sprintf('/request/%s/links', rawurlencode($requestId)), new \stdClass());
            $wizardUrl = $this->extractWizardUrl($linksResponse);
        }

        if ($wizardUrl === null) {
            throw new RuntimeException('Namirial OnBoarding no ha retornat cap enllaç d\'accés al wizard.');
        }

        $this->logger?->info('Sessió eIDV creada a Namirial OnBoarding API v2', [
            'request_id' => $requestId,
            'reference_id' => $referenceId,
            'user_id' => $user->getId(),
        ]);

        return new KycSessionDto(
            requestId: $requestId,
            referenceId: $referenceId,
            wizardUrl: $wizardUrl,
            isSimulated: false,
            rawResponse: json_encode($created) ?: null
        );
    }

    public function fetchVerificationVerdict(string $requestId): KycVerdictDto
    {
        if (str_starts_with($requestId, 'sim-') || !$this->isLiveConfigured()) {
            return new KycVerdictDto(
                requestId: $requestId,
                referenceId: null,
                status: KycStatus::APPROVED,
                providerStatus: 'COMPLETED',
                reason: 'Verificació d\'identitat completada en mode simulació.',
                isSimulated: true
            );
        }

        $encodedId = rawurlencode($requestId);
        $requestData = $this->requestJson('GET', sprintf('/request/%s', $encodedId));
        $providerStatus = strtoupper((string) ($requestData['status'] ?? 'UNKNOWN'));
        $referenceId = isset($requestData['referenceId']) ? (string) $requestData['referenceId'] : null;

        if (in_array($providerStatus, ['EXPIRED', 'FAILED', 'CANCELLED'], true)) {
            $reason = match ($providerStatus) {
                'EXPIRED' => 'La sol·licitud de verificació ha caducat abans de completar-se.',
                'CANCELLED' => 'El procés de verificació s\'ha cancel·lat.',
                default => 'La verificació documental o biomètrica ha fallat.',
            };

            return new KycVerdictDto(
                requestId: $requestId,
                referenceId: $referenceId,
                status: KycStatus::REJECTED,
                providerStatus: $providerStatus,
                reason: $reason,
                rawPayload: ['request' => $requestData]
            );
        }

        if ($providerStatus !== 'COMPLETED') {
            return new KycVerdictDto(
                requestId: $requestId,
                referenceId: $referenceId,
                status: KycStatus::PENDING,
                providerStatus: $providerStatus,
                reason: 'La verificació encara es troba en curs a Namirial OnBoarding.',
                rawPayload: ['request' => $requestData]
            );
        }

        $verdictsData = $this->tryGetJson(sprintf('/request/%s/verdicts', $encodedId));
        $identificationsData = $this->tryGetJson(sprintf('/request/%s/identityVerifications', $encodedId));

        return $this->resolveCompletedVerdict(
            requestId: $requestId,
            referenceId: $referenceId,
            providerStatus: $providerStatus,
            requestData: $requestData,
            verdictsData: $verdictsData,
            identificationsData: $identificationsData
        );
    }

    /**
     * @param array<string, mixed>|object|null $jsonBody
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, array|object|null $jsonBody = null): array
    {
        $options = [
            'headers' => [
                'X-Api-Key' => $this->apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'timeout' => self::DEFAULT_TIMEOUT_SECONDS,
        ];

        if ($jsonBody !== null) {
            $options['json'] = $jsonBody;
        }

        try {
            $response = $this->httpClient->request($method, $this->baseUrl . $path, $options);
            $data = $response->toArray();

            return \is_array($data) ? $data : [];
        } catch (Throwable $e) {
            $this->logger?->error('Error en comunicar amb Namirial OnBoarding API v2', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('No s\'ha pogut completar la comunicació amb Namirial OnBoarding: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function tryGetJson(string $path): array
    {
        try {
            return $this->requestJson('GET', $path);
        } catch (Throwable $e) {
            $this->logger?->warning('Endpoint auxiliar Namirial no disponible o buit', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractWizardUrl(array $payload): ?string
    {
        if (isset($payload['wizardUrl']) && \is_string($payload['wizardUrl']) && $payload['wizardUrl'] !== '') {
            return $payload['wizardUrl'];
        }

        if (isset($payload['url']) && \is_string($payload['url']) && $payload['url'] !== '') {
            return $payload['url'];
        }

        $links = $payload['links'] ?? ($payload['content'] ?? null);
        if (\is_array($links)) {
            foreach ($links as $entry) {
                if (\is_array($entry) && isset($entry['url']) && \is_string($entry['url']) && $entry['url'] !== '') {
                    return $entry['url'];
                }
            }
        }

        foreach ($payload as $entry) {
            if (\is_array($entry) && isset($entry['url']) && \is_string($entry['url']) && $entry['url'] !== '') {
                return $entry['url'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $requestData
     * @param array<string, mixed> $verdictsData
     * @param array<string, mixed> $identificationsData
     */
    private function resolveCompletedVerdict(
        string $requestId,
        ?string $referenceId,
        string $providerStatus,
        array $requestData,
        array $verdictsData,
        array $identificationsData
    ): KycVerdictDto {
        $verdicts = $this->normalizeList($verdictsData, 'verdicts');
        $identifications = $this->normalizeList($identificationsData, 'content', 'identityVerifications');

        $rejectionReason = $this->findRejectionReason($verdicts, $identifications);
        [$verifiedFirstName, $verifiedLastName, $score] = $this->extractVerifiedPersonData($identifications);

        if ($rejectionReason !== null) {
            return new KycVerdictDto(
                requestId: $requestId,
                referenceId: $referenceId,
                status: KycStatus::REJECTED,
                providerStatus: $providerStatus,
                reason: $rejectionReason,
                verifiedFirstName: $verifiedFirstName,
                verifiedLastName: $verifiedLastName,
                biometricMatchScore: $score,
                rawPayload: [
                    'request' => $requestData,
                    'verdicts' => $verdictsData,
                    'identityVerifications' => $identificationsData,
                ]
            );
        }

        return new KycVerdictDto(
            requestId: $requestId,
            referenceId: $referenceId,
            status: KycStatus::APPROVED,
            providerStatus: $providerStatus,
            reason: 'Identitat verificada favorablement amb control documental i prova de vida.',
            verifiedFirstName: $verifiedFirstName,
            verifiedLastName: $verifiedLastName,
            biometricMatchScore: $score,
            rawPayload: [
                'request' => $requestData,
                'verdicts' => $verdictsData,
                'identityVerifications' => $identificationsData,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array<string, mixed>>
     */
    private function normalizeList(array $data, string ...$preferredKeys): array
    {
        foreach ($preferredKeys as $key) {
            if (isset($data[$key]) && \is_array($data[$key])) {
                return array_values(array_filter($data[$key], '\is_array'));
            }
        }

        return array_is_list($data) ? array_values(array_filter($data, '\is_array')) : [];
    }

    /**
     * @param list<array<string, mixed>> $verdicts
     * @param list<array<string, mixed>> $identifications
     */
    private function findRejectionReason(array $verdicts, array $identifications): ?string
    {
        $negativeStatuses = ['REJECTED', 'FAILED', 'DENIED', 'NEGATIVE', 'MISMATCH', 'KO', 'INVALID', 'ERROR'];

        foreach ($verdicts as $verdict) {
            foreach (['outcome', 'verdict', 'status', 'result', 'action'] as $field) {
                $val = strtoupper((string) ($verdict[$field] ?? ''));
                if ($val !== '' && $this->matchesAnyToken($val, $negativeStatuses)) {
                    return $this->extractReasonText($verdict)
                        ?? sprintf('La resolució del veredicte ha resultat desfavorable (%s).', $val);
                }
            }
        }

        foreach ($identifications as $identification) {
            foreach (['verified', 'passed', 'matched', 'isValid'] as $boolField) {
                if (isset($identification[$boolField]) && $identification[$boolField] === false) {
                    return $this->extractReasonText($identification)
                        ?? 'Les dades del document d\'identitat o la comprovació facial no coincideixen.';
                }
            }

            foreach (['status', 'outcome', 'result', 'verificationStatus'] as $field) {
                $val = strtoupper((string) ($identification[$field] ?? ''));
                if ($val !== '' && $this->matchesAnyToken($val, $negativeStatuses)) {
                    return $this->extractReasonText($identification)
                        ?? sprintf('La verificació d\'identitat ha estat rebutjada (%s).', $val);
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $tokens
     */
    private function matchesAnyToken(string $haystack, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractReasonText(array $item): ?string
    {
        foreach (['reason', 'failureReason', 'errorMessage', 'message', 'detail', 'description'] as $key) {
            if (isset($item[$key]) && \is_string($item[$key]) && trim($item[$key]) !== '') {
                return trim($item[$key]);
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $identifications
     * @return array{0: ?string, 1: ?string, 2: ?float}
     */
    private function extractVerifiedPersonData(array $identifications): array
    {
        $firstName = null;
        $lastName = null;
        $score = null;

        foreach ($identifications as $identification) {
            $person = $identification['personData'] ?? ($identification['holder'] ?? $identification);
            if (\is_array($person)) {
                $firstName ??= isset($person['firstName']) && \is_string($person['firstName']) ? trim($person['firstName']) : null;
                $lastName ??= isset($person['lastName']) && \is_string($person['lastName']) ? trim($person['lastName']) : null;
            }

            foreach (['score', 'matchScore', 'biometricScore', 'similarity'] as $scoreKey) {
                if (isset($identification[$scoreKey]) && is_numeric($identification[$scoreKey])) {
                    $score = (float) $identification[$scoreKey];
                    break;
                }
            }
        }

        return [$firstName, $lastName, $score];
    }

    private function buildSimulatedWizardUrl(string $wizardUrlBack, string $requestId, string $referenceId): string
    {
        $resolved = str_replace(
            ['<requestId>', '<referenceId>', '<status>', '<actor>'],
            [$requestId, $referenceId, 'COMPLETED', 'tcIdentificationCustomer'],
            $wizardUrlBack
        );

        $separator = str_contains($resolved, '?') ? '&' : '?';
        $params = [];
        if (!str_contains($resolved, 'requestId=')) {
            $params['requestId'] = $requestId;
        }
        if (!str_contains($resolved, 'referenceId=')) {
            $params['referenceId'] = $referenceId;
        }
        $params['simulated'] = '1';

        return $resolved . $separator . http_build_query($params);
    }
}

