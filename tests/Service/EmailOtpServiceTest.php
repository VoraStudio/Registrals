<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Enum\EmailOtpVerificationStatus;
use App\Service\Security\EmailOtpService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

class EmailOtpServiceTest extends TestCase
{
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('carles@institutmontilivi.cat');
        $user->setFirstName('Carles');
        $user->setLastName('Mellado');
        $user->setAuthProvider('local');

        return $user;
    }

    public function testIssueAndVerifyRegistrationOtpMarksEmailVerifiedAndIsSingleUse(): void
    {
        $user = $this->createUser();
        $capturedCode = null;

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use (&$capturedCode): bool {
                $context = $email->getContext();
                $capturedCode = $context['code'] ?? null;

                return $email->getHtmlTemplate() === 'emails/security_otp.html.twig'
                    && $email->getTextTemplate() === 'emails/security_otp.txt.twig'
                    && is_string($capturedCode)
                    && preg_match('/^\d{6}$/', $capturedCode) === 1
                    && $context['purpose'] === EmailOtpPurpose::REGISTRATION_VERIFY;
            }));

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->once())
            ->method('log')
            ->with($user, UserActivityAction::EMAIL_VERIFIED, $this->anything(), $this->anything());

        $service = new EmailOtpService($em, $mailer, 'test-app-secret', $logger);

        $issueResult = $service->issueOtp($user, EmailOtpPurpose::REGISTRATION_VERIFY);
        self::assertTrue($issueResult['sent']);
        self::assertNull($issueResult['error']);
        self::assertNotNull($user->getEmailOtpHash());
        self::assertNotSame($capturedCode, $user->getEmailOtpHash());
        self::assertSame(EmailOtpPurpose::REGISTRATION_VERIFY, $user->getEmailOtpPurpose());
        self::assertFalse($user->isEmailVerified());

        // No ha de validar si s'intenta amb una finalitat diferent
        self::assertSame(
            EmailOtpVerificationStatus::EXPIRED_OR_MISSING,
            $service->verifyOtp($user, EmailOtpPurpose::LOGIN_2FA, (string) $capturedCode)
        );

        $verifyResult = $service->verifyOtp($user, EmailOtpPurpose::REGISTRATION_VERIFY, ' ' . $capturedCode . ' ');
        self::assertSame(EmailOtpVerificationStatus::VALID, $verifyResult);
        self::assertTrue($user->isEmailVerified());
        self::assertNull($user->getEmailOtpHash());
        self::assertNull($user->getEmailOtpPurpose());

        // Un sol ús
        self::assertSame(
            EmailOtpVerificationStatus::EXPIRED_OR_MISSING,
            $service->verifyOtp($user, EmailOtpPurpose::REGISTRATION_VERIFY, (string) $capturedCode)
        );
    }

    public function testLogin2faOtpVerifiesWithoutMutatingEmailVerifiedAt(): void
    {
        $user = $this->createUser();
        $verifiedAt = new \DateTimeImmutable('2026-10-01 10:00:00');
        $user->markEmailVerified($verifiedAt);
        $capturedCode = null;

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email) use (&$capturedCode): bool {
                $capturedCode = $email->getContext()['code'] ?? null;
                return $email->getContext()['purpose'] === EmailOtpPurpose::LOGIN_2FA;
            }));

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->exactly(2))->method('log');

        $service = new EmailOtpService($em, $mailer, 'test-app-secret', $logger);
        $service->issueOtp($user, EmailOtpPurpose::LOGIN_2FA);

        $status = $service->verifyOtp($user, EmailOtpPurpose::LOGIN_2FA, (string) $capturedCode);
        self::assertSame(EmailOtpVerificationStatus::VALID, $status);
        self::assertSame($verifiedAt, $user->getEmailVerifiedAt());
    }

    public function testWrongCodeInvalidatesAfterMaxAttempts(): void
    {
        $user = $this->createUser();
        $capturedCode = null;

        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (TemplatedEmail $email) use (&$capturedCode): void {
            $capturedCode = $email->getContext()['code'] ?? null;
        });

        $service = new EmailOtpService($em, $mailer, 'test-app-secret');
        $service->issueOtp($user, EmailOtpPurpose::LOGIN_2FA);

        $wrongCode = $capturedCode === '000000' ? '111111' : '000000';

        for ($i = 1; $i < EmailOtpService::MAX_ATTEMPTS; $i++) {
            self::assertSame(
                EmailOtpVerificationStatus::INVALID,
                $service->verifyOtp($user, EmailOtpPurpose::LOGIN_2FA, $wrongCode)
            );
            self::assertSame($i, $user->getEmailOtpAttempts());
            self::assertNotNull($user->getEmailOtpHash());
        }

        // Al 5è intent fallit invalida el codi completament
        self::assertSame(
            EmailOtpVerificationStatus::MAX_ATTEMPTS_EXCEEDED,
            $service->verifyOtp($user, EmailOtpPurpose::LOGIN_2FA, $wrongCode)
        );
        self::assertNull($user->getEmailOtpHash());
        self::assertSame(0, $user->getEmailOtpAttempts());
    }

    public function testResendCooldownRespectsWaitIntervalAndMailerFailureAllowsImmediateRetry(): void
    {
        $user = $this->createUser();
        $em = $this->createMock(EntityManagerInterface::class);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $service = new EmailOtpService($em, $mailer, 'test-app-secret');

        $first = $service->issueOtp($user, EmailOtpPurpose::REGISTRATION_VERIFY, respectCooldown: true);
        self::assertTrue($first['sent']);

        $second = $service->issueOtp($user, EmailOtpPurpose::REGISTRATION_VERIFY, respectCooldown: true);
        self::assertFalse($second['sent']);
        self::assertGreaterThan(0, $second['cooldownRemaining']);

        // Si el transport falla, allibera emailOtpSentAt per permetre reintent immediat
        $user2 = $this->createUser();
        $failingMailer = $this->createMock(MailerInterface::class);
        $failingMailer->method('send')->willThrowException(new TransportException('SMTP timeout'));
        $failingService = new EmailOtpService($em, $failingMailer, 'test-app-secret');

        $failed = $failingService->issueOtp($user2, EmailOtpPurpose::REGISTRATION_VERIFY);
        self::assertFalse($failed['sent']);
        self::assertSame(0, $failed['cooldownRemaining']);
        self::assertNotNull($failed['error']);
        self::assertNull($user2->getEmailOtpSentAt());
    }

    public function testMaskEmailFormatsCleanly(): void
    {
        $service = new EmailOtpService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(MailerInterface::class),
            'secret'
        );

        self::assertSame('cm******@institutmontilivi.cat', $service->maskEmail('cmellado@institutmontilivi.cat'));
        self::assertSame('a**@x.cat', $service->maskEmail('ab@x.cat'));
    }
}

