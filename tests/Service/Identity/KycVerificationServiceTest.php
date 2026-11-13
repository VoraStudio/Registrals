<?php

declare(strict_types=1);

namespace App\Tests\Service\Identity;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\DTO\KycSessionDto;
use App\DTO\KycVerdictDto;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\KycStatus;
use App\Infrastructure\Adapter\Identity\IdentityVerificationGatewayInterface;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\Identity\KycVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class KycVerificationServiceTest extends TestCase
{
    private function createService(
        IdentityVerificationGatewayInterface $gateway,
        ?UserRepository $userRepo = null,
        ?PlanRepository $planRepo = null,
        ?EntityManagerInterface $em = null,
        ?UserActivityLogger $auditLogger = null
    ): KycVerificationService {
        return new KycVerificationService(
            identityGateway: $gateway,
            entityManager: $em ?? $this->createMock(EntityManagerInterface::class),
            userRepository: $userRepo ?? $this->createMock(UserRepository::class),
            planRepository: $planRepo ?? $this->createMock(PlanRepository::class),
            activityLogger: $auditLogger,
            logger: new NullLogger()
        );
    }

    public function testFreeUserWithoutCreditCannotStartWithoutCheckout(): void
    {
        $freePlan = (new Plan('Free', 'free', 0, 20, 1, 1))->setKycIncluded(0)->setKycPriceCents(350);
        $user = (new User())
            ->setEmail('free@registrals.cat')
            ->setFirstName('Anna')
            ->setLastName('Puig')
            ->setPlan($freePlan);

        $gateway = $this->createMock(IdentityVerificationGatewayInterface::class);
        $gateway->expects($this->never())->method('startVerificationSession');

        $service = $this->createService($gateway);

        $this->assertFalse($service->canStartWithoutCheckout($user));
        $this->expectException(DomainException::class);
        $service->initiateVerification($user, 'https://registrals.cat/callback');
    }

    public function testInitiateVerificationThrowsWhenTaxIdIsMissing(): void
    {
        $plusPlan = (new Plan('Plus', 'plus', 1900, 100, 5, 5))->setKycIncluded(1);
        $user = (new User())
            ->setEmail('sense-nif@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setPlan($plusPlan);

        $gateway = $this->createMock(IdentityVerificationGatewayInterface::class);
        $gateway->expects($this->never())->method('startVerificationSession');

        $service = $this->createService($gateway);

        $this->expectException(DomainException::class);
        $service->initiateVerification($user, 'https://registrals.test/app/perfil');
    }

    public function testFreeUserWithPaidCreditConsumesCreditAndLogsInitiated(): void
    {
        $freePlan = (new Plan('Free', 'free', 0, 20, 1, 1))->setKycIncluded(0)->setKycPriceCents(350);
        $user = (new User())
            ->setEmail('free-paid@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setTaxId('12345678Z')
            ->setPlan($freePlan)
            ->incrementKycPaidCredits();

        $gateway = $this->createMock(IdentityVerificationGatewayInterface::class);
        $gateway->expects($this->once())
            ->method('startVerificationSession')
            ->willReturn(new KycSessionDto('req-real-1', 'REG-KYC-1', 'https://wizard.namirialonboarding.com/go'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($user);
        $em->expects($this->once())->method('flush');

        $auditLogger = $this->createMock(UserActivityLogger::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with($user, UserActivityAction::KYC_INITIATED, $this->anything(), $this->callback(fn (array $d) => ($d['client_ip'] ?? null) === '127.0.0.1'));

        $service = $this->createService($gateway, em: $em, auditLogger: $auditLogger);
        $session = $service->initiateVerification($user, 'https://registrals.cat/callback', '127.0.0.1');

        $this->assertSame('req-real-1', $session->requestId);
        $this->assertSame(0, $user->getKycPaidCredits());
        $this->assertSame(KycStatus::PENDING, $user->getKycStatus());
        $this->assertSame('req-real-1', $user->getSignaturitKycId());
    }

    public function testSyncUpdatesApprovedAndRejectedIdempotentlyWithAuditLogs(): void
    {
        $user = (new User())
            ->setEmail('plus@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado Bosch')
            ->setSignaturitKycId('req-approve-1')
            ->setKycStatus(KycStatus::PENDING);

        $gateway = $this->createMock(IdentityVerificationGatewayInterface::class);
        $gateway->expects($this->exactly(2))
            ->method('fetchVerificationVerdict')
            ->with('req-approve-1')
            ->willReturn(new KycVerdictDto(
                requestId: 'req-approve-1',
                referenceId: 'REG-KYC-1',
                status: KycStatus::APPROVED,
                providerStatus: 'COMPLETED',
                reason: 'Identitat verificada.',
                biometricMatchScore: 99.1
            ));

        $auditLogger = $this->createMock(UserActivityLogger::class);
        // Nomes 1 log tot i executar sync dues vegades (idempotent)
        $auditLogger->expects($this->once())
            ->method('log')
            ->with($user, UserActivityAction::KYC_APPROVED, $this->anything(), $this->callback(fn (array $d) => ($d['biometric_match_score'] ?? null) === 99.1 && ($d['client_ip'] ?? null) === '10.0.0.1'));

        $service = $this->createService($gateway, auditLogger: $auditLogger);
        $service->syncUserVerificationStatus($user, '10.0.0.1', 'callback');
        $service->syncUserVerificationStatus($user, '10.0.0.1', 'webhook');

        $this->assertSame(KycStatus::APPROVED, $user->getKycStatus());
        $this->assertTrue($user->isIdentityVerified());
        $this->assertNotNull($user->getIdentityVerifiedAt());
    }

    public function testHandleWebhookResolvesUserAndRejectsWhenDenied(): void
    {
        $user = (new User())
            ->setEmail('rejected@registrals.cat')
            ->setFirstName('Joan')
            ->setLastName('Prova')
            ->setSignaturitKycId('req-rej-9')
            ->setKycStatus(KycStatus::PENDING);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->with(['signaturitKycId' => 'req-rej-9'])->willReturn($user);

        $gateway = $this->createMock(IdentityVerificationGatewayInterface::class);
        $gateway->method('fetchVerificationVerdict')
            ->with('req-rej-9')
            ->willReturn(new KycVerdictDto(
                requestId: 'req-rej-9',
                referenceId: null,
                status: KycStatus::REJECTED,
                providerStatus: 'COMPLETED',
                reason: 'Nom discordant amb el document.'
            ));

        $auditLogger = $this->createMock(UserActivityLogger::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with($user, UserActivityAction::KYC_REJECTED, $this->stringContains('Nom discordant'));

        $service = $this->createService($gateway, userRepo: $userRepo, auditLogger: $auditLogger);
        $handled = $service->handleWebhookPayload(['requestId' => 'req-rej-9'], '8.8.8.8');

        $this->assertTrue($handled);
        $this->assertSame(KycStatus::REJECTED, $user->getKycStatus());
        $this->assertSame('Nom discordant amb el document.', $user->getKycLastReason());
    }
}
