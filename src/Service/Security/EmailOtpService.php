<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Enum\EmailOtpVerificationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Servei unificat per a la generació, enviament i verificació de codis OTP de 6 dígits per correu electrònic
 * (validació obligatòria de registre i segon factor opcional d'inici de sessió 2FA).
 */
class EmailOtpService
{
    public const OTP_TTL_SECONDS = 900; // 15 minuts
    public const RESEND_COOLDOWN_SECONDS = 60; // 60 segons entre reenviaments
    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly string $appSecret,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $mailerFromAddress = 'info@registrals.com',
        private readonly string $mailerFromName = 'Registrals'
    ) {
    }

    /**
     * Genera i envia un codi OTP numèric de 6 dígits per correu electrònic.
     *
     * @return array{sent: bool, cooldownRemaining: int, error: ?string}
     */
    public function issueOtp(User $user, EmailOtpPurpose $purpose, bool $respectCooldown = false): array
    {
        if ($respectCooldown) {
            $remaining = $this->getResendCooldownRemaining($user, $purpose);
            if ($remaining > 0) {
                return [
                    'sent' => false,
                    'cooldownRemaining' => $remaining,
                    'error' => null,
                ];
            }
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify(sprintf('+%d seconds', self::OTP_TTL_SECONDS));

        $user->setEmailOtpHash($this->computeHash($user, $purpose, $code))
            ->setEmailOtpPurpose($purpose)
            ->setEmailOtpExpiresAt($expiresAt)
            ->setEmailOtpSentAt($now)
            ->setEmailOtpAttempts(0)
            ->setUpdatedAt($now);

        $this->entityManager->flush();

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject($purpose->emailSubject())
                ->htmlTemplate('emails/security_otp.html.twig')
                ->textTemplate('emails/security_otp.txt.twig')
                ->context([
                    'user' => $user,
                    'purpose' => $purpose,
                    'code' => $code,
                    'expiresInMinutes' => (int) (self::OTP_TTL_SECONDS / 60),
                ]);

            $this->mailer->send($email);

            if ($purpose === EmailOtpPurpose::LOGIN_2FA) {
                $this->recordAudit(
                    $user,
                    UserActivityAction::TWO_FACTOR_CHALLENGE_SENT,
                    sprintf('Enviament de codi 2FA de 6 dígits a %s', $user->getEmail()),
                    ['expires_at' => $expiresAt->format(\DateTimeInterface::ATOM)]
                );
            }

            return [
                'sent' => true,
                'cooldownRemaining' => self::RESEND_COOLDOWN_SECONDS,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $this->logger?->warning('Error en enviar el codi OTP per correu electrònic: ' . $e->getMessage(), [
                'user_id' => $user->getId(),
                'purpose' => $purpose->value,
            ]);

            // Permetem reintent immediat si el servidor SMTP ha fallat puntualment
            $user->setEmailOtpSentAt(null);
            $this->entityManager->flush();

            return [
                'sent' => false,
                'cooldownRemaining' => 0,
                'error' => 'No s\'ha pogut enviar el correu electrònic amb el codi en aquest moment. Torna-ho a provar amb el botó de reenviament.',
            ];
        }
    }

    /**
     * Comprova el codi OTP de 6 dígits introduït i aplica el límit d'intents i l'única utilització.
     */
    public function verifyOtp(User $user, EmailOtpPurpose $purpose, string $rawCode): EmailOtpVerificationStatus
    {
        if (!$user->hasActiveEmailOtp($purpose)) {
            return EmailOtpVerificationStatus::EXPIRED_OR_MISSING;
        }

        $normalizedCode = preg_replace('/\s+/', '', trim($rawCode)) ?? '';
        if (strlen($normalizedCode) !== 6 || !ctype_digit($normalizedCode)) {
            return $this->registerFailedAttempt($user, $purpose);
        }

        $expectedHash = $this->computeHash($user, $purpose, $normalizedCode);
        if (!hash_equals((string) $user->getEmailOtpHash(), $expectedHash)) {
            return $this->registerFailedAttempt($user, $purpose);
        }

        $now = new \DateTimeImmutable();
        $user->clearEmailOtp()->setUpdatedAt($now);

        if ($purpose === EmailOtpPurpose::REGISTRATION_VERIFY) {
            $user->markEmailVerified($now);
            $this->entityManager->flush();
            $this->recordAudit(
                $user,
                UserActivityAction::EMAIL_VERIFIED,
                sprintf('Verificació d\'adreça de correu completada per a %s', $user->getEmail()),
                ['verified_at' => $now->format(\DateTimeInterface::ATOM)]
            );
        } else {
            $this->entityManager->flush();
            $this->recordAudit(
                $user,
                UserActivityAction::TWO_FACTOR_VERIFIED,
                sprintf('Validació de doble factor (2FA) per correu completada per a %s', $user->getEmail()),
                ['verified_at' => $now->format(\DateTimeInterface::ATOM)]
            );
        }

        return EmailOtpVerificationStatus::VALID;
    }

    public function getResendCooldownRemaining(User $user, EmailOtpPurpose $purpose): int
    {
        $sentAt = $user->getEmailOtpSentAt();
        if (!$user->hasActiveEmailOtp($purpose) || $sentAt === null) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $elapsed = $now->getTimestamp() - $sentAt->getTimestamp();
        if ($elapsed >= self::RESEND_COOLDOWN_SECONDS) {
            return 0;
        }

        return max(1, self::RESEND_COOLDOWN_SECONDS - $elapsed);
    }

    public function maskEmail(string $email): string
    {
        return PrivacyMasker::maskEmail($email);
    }

    private function registerFailedAttempt(User $user, EmailOtpPurpose $purpose): EmailOtpVerificationStatus
    {
        $attempts = $user->getEmailOtpAttempts() + 1;
        $user->setEmailOtpAttempts($attempts);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $user->clearEmailOtp();
            $this->entityManager->flush();

            if ($purpose === EmailOtpPurpose::LOGIN_2FA) {
                $this->recordAudit(
                    $user,
                    UserActivityAction::TWO_FACTOR_FAILED,
                    sprintf('Codi 2FA invalidat per excés d\'intents (%d) per a %s', self::MAX_ATTEMPTS, $user->getEmail()),
                    ['attempts' => $attempts]
                );
            }

            return EmailOtpVerificationStatus::MAX_ATTEMPTS_EXCEEDED;
        }

        $this->entityManager->flush();

        if ($purpose === EmailOtpPurpose::LOGIN_2FA) {
            $this->recordAudit(
                $user,
                UserActivityAction::TWO_FACTOR_FAILED,
                sprintf('Intent erroni de codi 2FA (%d/%d) per a %s', $attempts, self::MAX_ATTEMPTS, $user->getEmail()),
                ['attempts' => $attempts]
            );
        }

        return EmailOtpVerificationStatus::INVALID;
    }

    private function computeHash(User $user, EmailOtpPurpose $purpose, string $code): string
    {
        $payload = sprintf('%s|%s|%s', $purpose->value, $user->getUuid()->toRfc4122(), $code);

        return hash_hmac('sha256', $payload, $this->appSecret);
    }

    /**
     * @param array<string, mixed>|null $details
     */
    private function recordAudit(User $user, UserActivityAction $action, string $description, ?array $details = null): void
    {
        if ($this->activityLogger === null) {
            return;
        }

        try {
            $this->activityLogger->log($user, $action, $description, $details);
        } catch (\Throwable $e) {
            $this->logger?->warning('No s\'ha pogut desar el registre d\'auditoria OTP: ' . $e->getMessage());
        }
    }
}

