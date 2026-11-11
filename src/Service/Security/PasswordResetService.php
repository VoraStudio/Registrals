<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetService
{
    public const TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly string $mailerFromAddress = 'info@registrals.com',
        private readonly string $mailerFromName = 'Registrals'
    ) {
    }

    /**
     * Genera un token efímer d'un sol ús (hash SHA-256 a la BDD) i envia l'enllaç de recuperació.
     * Mai filtra ni en excepció ni en resposta si el correu existeix al sistema.
     */
    public function requestReset(string $rawEmail): void
    {
        $normalizedEmail = mb_strtolower(trim($rawEmail));
        if ($normalizedEmail === '') {
            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $normalizedEmail]);
        if (!$user instanceof User || !$user->isActive()) {
            return;
        }

        // Els comptes vinculats amb proveïdors externs (Google OAuth) no gestionen contrasenya local
        if ($user->getAuthProvider() !== 'local') {
            if ($user->getResetPasswordTokenHash() !== null || $user->getResetPasswordExpiresAt() !== null) {
                $user->clearResetPasswordToken();
                $this->entityManager->flush();
            }

            $loginUrl = $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $this->sendResetEmail($user, $loginUrl, isOAuthOnly: true);
            $this->recordAudit($user, UserActivityAction::PASSWORD_RESET_REQUESTED, 'Sol·licitud de recuperació en compte vinculat a Google OAuth', ['oauth_only' => true]);
            return;
        }

        $rawToken = bin2hex(random_bytes(32));
        $expiresAt = (new \DateTimeImmutable())->modify(sprintf('+%d seconds', self::TOKEN_TTL_SECONDS));

        $user->setResetPasswordTokenHash(hash('sha256', $rawToken))
            ->setResetPasswordExpiresAt($expiresAt)
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $rawToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->sendResetEmail($user, $resetUrl, isOAuthOnly: false);
        $this->recordAudit(
            $user,
            UserActivityAction::PASSWORD_RESET_REQUESTED,
            sprintf('Enviament d\'enllaç temporal de recuperació de contrasenya a %s', $user->getEmail()),
            ['expires_at' => $expiresAt->format(\DateTimeInterface::ATOM)]
        );
    }

    public function findValidUserByRawToken(string $rawToken): ?User
    {
        if ($rawToken === '' || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return null;
        }

        $user = $this->userRepository->findOneByResetTokenHash(hash('sha256', $rawToken));
        if (!$user instanceof User || !$user->isActive() || $user->getAuthProvider() !== 'local' || !$user->isResetPasswordTokenValid()) {
            return null;
        }

        return $user;
    }

    public function completeReset(User $user, string $newPlainPassword): void
    {
        $hashed = $this->passwordHasher->hashPassword($user, $newPlainPassword);

        $user->setPassword($hashed)
            ->clearResetPasswordToken()
            ->clearEmailOtp()
            ->markEmailVerified()
            ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->recordAudit(
            $user,
            UserActivityAction::PASSWORD_CHANGED,
            'Restabliment de contrasenya completat mitjançant enllaç de recuperació',
            ['context' => 'password_reset_recovery']
        );
    }

    private function sendResetEmail(User $user, string $actionUrl, bool $isOAuthOnly): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject($isOAuthOnly
                    ? 'Accés al teu compte — Registrals'
                    : 'Restableix la teva contrasenya — Registrals')
                ->htmlTemplate('emails/reset_password.html.twig')
                ->textTemplate('emails/reset_password.txt.twig')
                ->context([
                    'user' => $user,
                    'actionUrl' => $actionUrl,
                    'isOAuthOnly' => $isOAuthOnly,
                    'expiresInMinutes' => (int) (self::TOKEN_TTL_SECONDS / 60),
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error en enviar el correu de recuperació de contrasenya: ' . $e->getMessage(), [
                'user_id' => $user->getId(),
            ]);
        }
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
            $this->logger?->warning('No s\'ha pogut desar el log d\'auditoria de recuperació: ' . $e->getMessage());
        }
    }
}

