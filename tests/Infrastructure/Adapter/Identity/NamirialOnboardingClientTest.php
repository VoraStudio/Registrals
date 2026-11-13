<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Adapter\Identity;

use App\Entity\User;
use App\Enum\KycStatus;
use App\Infrastructure\Adapter\Identity\NamirialOnboardingClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class NamirialOnboardingClientTest extends TestCase
{
    public function testSimulatedSessionGeneratesSafeWizardUrlAndVerdict(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $client = new NamirialOnboardingClient(
            httpClient: $httpClient,
            baseUrl: 'https://test-eu-ie1-api.namirialonboarding.com/api/v2',
            apiKey: '',
            requestTypeId: '',
            webhookSecret: 'secret-123',
            logger: new NullLogger()
        );

        $this->assertFalse($client->isLiveConfigured());

        $user = (new User())
            ->setEmail('titular@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch');

        $session = $client->startVerificationSession(
            $user,
            'REG-KYC-1-ABCD',
            'https://registrals.cat/app/perfil/verificacio/retorn?requestId=<requestId>&referenceId=<referenceId>&status=<status>'
        );

        $this->assertTrue($session->isSimulated);
        $this->assertStringStartsWith('sim-', $session->requestId);
        $this->assertSame('REG-KYC-1-ABCD', $session->referenceId);
        $this->assertStringContainsString('simulated=1', $session->wizardUrl);
        $this->assertStringContainsString($session->requestId, $session->wizardUrl);
        $this->assertStringContainsString('REG-KYC-1-ABCD', $session->wizardUrl);

        $verdict = $client->fetchVerificationVerdict($session->requestId);
        $this->assertTrue($verdict->isSimulated);
        $this->assertSame(KycStatus::APPROVED, $verdict->status);
    }

    public function testStartSessionRequiresFirstAndLastName(): void
    {
        $client = new NamirialOnboardingClient(
            httpClient: $this->createMock(HttpClientInterface::class),
            apiKey: '',
            requestTypeId: '',
            logger: new NullLogger()
        );

        $user = (new User())
            ->setEmail('sense.nom@registrals.cat')
            ->setFirstName('')
            ->setLastName('Test');

        $this->expectException(InvalidArgumentException::class);
        $client->startVerificationSession($user, 'REG-KYC-2', 'https://registrals.cat/callback');
    }

    public function testLiveSessionCallsPostRequestsAndActorLinksFallback(): void
    {
        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->method('toArray')->willReturn([
            'id' => 'req-uuid-999',
            'status' => 'CREATED',
        ]);

        $linksResponse = $this->createMock(ResponseInterface::class);
        $linksResponse->method('toArray')->willReturn([
            'links' => [
                [
                    'actor' => 'operator',
                    'link' => 'https://wizard.namirialonboarding.com/session/operator',
                ],
                [
                    'actor' => 'userToIdentify',
                    'link' => 'https://wizard.namirialonboarding.com/session/abc',
                    'expiresAfter' => '2026-12-11T23:41:20.432450Z',
                ],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use ($createResponse, $linksResponse) {
                $this->assertSame('demo-api-key', $options['headers']['X-Api-Key']);
                $this->assertSame('POST', $method);

                if (str_ends_with($url, '/requests')) {
                    $this->assertSame('req-type-check', $options['json']['requestTypeId']);
                    $this->assertSame('REG-KYC-10-XYZ', $options['json']['referenceId']);
                    $this->assertSame('Carles', $options['json']['parameters']['firstName']);
                    $this->assertSame('Mellado Bosch', $options['json']['parameters']['lastName']);
                    $this->assertSame('12345678Z', $options['json']['parameters']['personalNumber']);
                    $this->assertSame('https://registrals.cat/callback', $options['json']['settings']['wizardUrlBack']);

                    return $createResponse;
                }

                $this->assertSame('https://test-eu-ie1-api.namirialonboarding.com/api/v2/request/req-uuid-999/links', $url);

                return $linksResponse;
            });

        $client = new NamirialOnboardingClient(
            httpClient: $httpClient,
            baseUrl: 'https://test-eu-ie1-api.namirialonboarding.com/api/v2',
            apiKey: 'demo-api-key',
            requestTypeId: 'req-type-check',
            webhookSecret: 'whsec-demo',
            logger: new NullLogger()
        );

        $this->assertTrue($client->isLiveConfigured());

        $user = (new User())
            ->setEmail('carles@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setTaxId(' 12345678-z ')
            ->setPhone('+34600111222');

        $session = $client->startVerificationSession($user, 'REG-KYC-10-XYZ', 'https://registrals.cat/callback');

        $this->assertFalse($session->isSimulated);
        $this->assertSame('req-uuid-999', $session->requestId);
        $this->assertSame('https://wizard.namirialonboarding.com/session/abc', $session->wizardUrl);
    }

    public function testLiveSessionExcludesCompanyCifFromPersonalNumber(): void
    {
        $createResponse = $this->createMock(ResponseInterface::class);
        $createResponse->method('toArray')->willReturn([
            'id' => 'req-uuid-cif',
            'links' => [
                ['actor' => 'userToIdentify', 'link' => 'https://wizard.namirialonboarding.com/session/cif'],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use ($createResponse) {
                $this->assertArrayNotHasKey('personalNumber', $options['json']['parameters']);

                return $createResponse;
            });

        $client = new NamirialOnboardingClient(
            httpClient: $httpClient,
            baseUrl: 'https://test-eu-ie1-api.namirialonboarding.com/api/v2',
            apiKey: 'demo-api-key',
            requestTypeId: 'req-type-check',
            logger: new NullLogger()
        );

        $user = (new User())
            ->setEmail('empresa@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setTaxId('B12345678');

        $session = $client->startVerificationSession($user, 'REG-KYC-11-CIF', 'https://registrals.cat/callback');
        $this->assertSame('req-uuid-cif', $session->requestId);
    }

    public function testLiveFetchVerdictHandlesRunningExpiredCompletedApprovedAndRejectedVerdicts(): void
    {
        $clientFor = function (array $map): NamirialOnboardingClient {
            $httpClient = $this->createMock(HttpClientInterface::class);
            $httpClient->method('request')->willReturnCallback(function (string $method, string $url) use ($map) {
                $response = $this->createMock(ResponseInterface::class);
                foreach ($map as $suffix => $payload) {
                    if (str_ends_with($url, $suffix)) {
                        $response->method('toArray')->willReturn($payload);
                        return $response;
                    }
                }
                throw new RuntimeException('Unexpected URL: ' . $url);
            });

            return new NamirialOnboardingClient(
                httpClient: $httpClient,
                baseUrl: 'https://test-eu-ie1-api.namirialonboarding.com/api/v2',
                apiKey: 'demo-api-key',
                requestTypeId: 'req-type-check',
                logger: new NullLogger()
            );
        };

        $running = $clientFor([
            '/request/r1' => ['id' => 'r1', 'status' => 'RUNNING', 'referenceId' => 'REF-1'],
        ])->fetchVerificationVerdict('r1');
        $this->assertSame(KycStatus::PENDING, $running->status);

        $expired = $clientFor([
            '/request/r2' => ['id' => 'r2', 'status' => 'EXPIRED', 'referenceId' => 'REF-2'],
        ])->fetchVerificationVerdict('r2');
        $this->assertSame(KycStatus::REJECTED, $expired->status);

        $cancelledOcr = $clientFor([
            '/request/r2b' => [
                'id' => 'r2b',
                'status' => 'CANCELLED',
                'referenceId' => 'REF-2B',
                'terminationReason' => 'ERROR_IDENTITY_ID_SELF_IMAGE_NO_TEXT_FOUND',
            ],
        ])->fetchVerificationVerdict('r2b');
        $this->assertSame(KycStatus::REJECTED, $cancelledOcr->status);
        $this->assertStringContainsString('ERROR_IDENTITY_ID_SELF_IMAGE_NO_TEXT_FOUND', (string) $cancelledOcr->reason);

        $approved = $clientFor([
            '/request/r3' => ['id' => 'r3', 'status' => 'COMPLETED', 'referenceId' => 'REF-3'],
            '/request/r3/verdicts' => ['verdicts' => [['outcome' => 'POSITIVE']]],
            '/request/r3/identityVerifications' => ['content' => [['status' => 'VERIFIED', 'score' => 98.4, 'personData' => ['firstName' => 'Carles', 'lastName' => 'Mellado Bosch']]]],
        ])->fetchVerificationVerdict('r3');
        $this->assertSame(KycStatus::APPROVED, $approved->status);
        $this->assertSame('Carles', $approved->verifiedFirstName);
        $this->assertSame('Mellado Bosch', $approved->verifiedLastName);
        $this->assertSame(98.4, $approved->biometricMatchScore);

        $rejectedDespiteCompleted = $clientFor([
            '/request/r4' => ['id' => 'r4', 'status' => 'COMPLETED', 'referenceId' => 'REF-4'],
            '/request/r4/verdicts' => ['verdicts' => [['outcome' => 'REJECTED', 'reason' => 'Name mismatch against official ID document.']]],
            '/request/r4/identityVerifications' => ['content' => [['matched' => false]]],
        ])->fetchVerificationVerdict('r4');
        $this->assertSame(KycStatus::REJECTED, $rejectedDespiteCompleted->status);
        $this->assertSame('Name mismatch against official ID document.', $rejectedDespiteCompleted->reason);
    }

    public function testVerifyWebhookSecretIsFailClosedAndConstantTime(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $emptyClient = new NamirialOnboardingClient($httpClient, webhookSecret: '');
        $this->assertFalse($emptyClient->verifyWebhookSecret(''));
        $this->assertFalse($emptyClient->verifyWebhookSecret('anything'));

        $configuredClient = new NamirialOnboardingClient($httpClient, webhookSecret: 'whsec_super_secret');
        $this->assertFalse($configuredClient->verifyWebhookSecret(null));
        $this->assertFalse($configuredClient->verifyWebhookSecret('wrong'));
        $this->assertTrue($configuredClient->verifyWebhookSecret('whsec_super_secret'));
    }
}

