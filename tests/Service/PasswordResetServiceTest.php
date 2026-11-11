<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetServiceTest extends TestCase
{
    private function createUser(string $email = 'carles@registrals.cat', string $authProvider = 'local'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Carles');
        $user->setLastName('Mellado');
        $user->setAuthProvider($authProvider);
        if ($authProvider === 'local') {
            $user->setPassword('old_hashed_password');
        }

        return $user;
    }

    public function testRequestResetIgnoresUnknownEmailWithoutSendingMail(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => 'desconegut@registrals.cat'])
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $service = new PasswordResetService(
            $userRepo,
            $em,
            $this->createMock(UserPasswordHasherInterface::class),
            $mailer,
            $this->createMock(UrlGeneratorInterface::class)
        );

        $service->requestReset('  Desconegut@Registrals.cat ');
    }

    public function testRequestResetIgnoresBlockedUser(): void
    {
        $user = $this->createUser();
        $user->setIsActive(false);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $service = new PasswordResetService(
            $userRepo,
            $em,
            $this->createMock(UserPasswordHasherInterface::class),
            $mailer,
            $this->createMock(UrlGeneratorInterface::class)
        );

        $service->requestReset('carles@registrals.cat');
    }

    public function testRequestResetStoresSha256TokenHashAndSendsRawTokenInEmail(): void
    {
        $user = $this->createUser();

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $capturedRawToken = null;
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with(
                'app_reset_password',
                $this->callback(function (array $params) use (&$capturedRawToken): bool {
                    $this->assertArrayHasKey('token', $params);
                    $capturedRawToken = $params['token'];
                    $this->assertSame(64, strlen($capturedRawToken));
                    $this->assertTrue(ctype_xdigit($capturedRawToken));
                    return true;
                }),
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            ->willReturn('https://www.registrals.com/reset-password/rawtoken');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email): bool {
                $context = $email->getContext();
                $from = $email->getFrom();
                $this->assertCount(1, $from);
                $this->assertSame('info@registrals.com', $from[0]->getAddress());
                $this->assertFalse($context['isOAuthOnly']);
                $this->assertSame('https://www.registrals.com/reset-password/rawtoken', $context['actionUrl']);
                $this->assertSame('emails/reset_password.html.twig', $email->getHtmlTemplate());
                return true;
            }));

        $auditLogger = $this->createMock(UserActivityLogger::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with($user, UserActivityAction::PASSWORD_RESET_REQUESTED, $this->anything(), $this->anything());

        $service = new PasswordResetService(
            $userRepo,
            $em,
            $this->createMock(UserPasswordHasherInterface::class),
            $mailer,
            $urlGenerator,
            $auditLogger
        );

        $service->requestReset('carles@registrals.cat');

        $this->assertNotNull($capturedRawToken);
        $this->assertSame(hash('sha256', (string) $capturedRawToken), $user->getResetPasswordTokenHash());
        $this->assertTrue($user->isResetPasswordTokenValid());
    }

    public function testRequestResetForGoogleOnlyUserSendsOAuthNoticeWithoutGeneratingToken(): void
    {
        $user = $this->createUser('oauth@registrals.cat', 'google');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects($this->once())
            ->method('generate')
            ->with('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('https://www.registrals.com/login');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (TemplatedEmail $email): bool {
                $context = $email->getContext();
                $this->assertTrue($context['isOAuthOnly']);
                $this->assertSame('https://www.registrals.com/login', $context['actionUrl']);
                return true;
            }));

        $service = new PasswordResetService(
            $userRepo,
            $em,
            $this->createMock(UserPasswordHasherInterface::class),
            $mailer,
            $urlGenerator
        );

        $service->requestReset('oauth@registrals.cat');

        $this->assertNull($user->getResetPasswordTokenHash());
        $this->assertNull($user->getResetPasswordExpiresAt());
    }

    public function testFindValidUserByRawTokenValidatesFormatExpirationAndActiveStatus(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $validUser = $this->createUser();
        $validUser->setResetPasswordTokenHash(hash('sha256', $rawToken))
            ->setResetPasswordExpiresAt(new \DateTimeImmutable('+30 minutes'));

        $expiredUser = $this->createUser('expired@registrals.cat');
        $expiredToken = bin2hex(random_bytes(32));
        $expiredUser->setResetPasswordTokenHash(hash('sha256', $expiredToken))
            ->setResetPasswordExpiresAt(new \DateTimeImmutable('-1 minute'));

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneByResetTokenHash')->willReturnCallback(
            function (string $hash) use ($rawToken, $validUser, $expiredToken, $expiredUser): ?User {
                if ($hash === hash('sha256', $rawToken)) {
                    return $validUser;
                }
                if ($hash === hash('sha256', $expiredToken)) {
                    return $expiredUser;
                }
                return null;
            }
        );

        $service = new PasswordResetService(
            $userRepo,
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(MailerInterface::class),
            $this->createMock(UrlGeneratorInterface::class)
        );

        $this->assertNull($service->findValidUserByRawToken('invalid-short'));
        $this->assertNull($service->findValidUserByRawToken($expiredToken));
        $this->assertSame($validUser, $service->findValidUserByRawToken($rawToken));

        $validUser->setIsActive(false);
        $this->assertNull($service->findValidUserByRawToken($rawToken));
    }

    public function testCompleteResetHashesPasswordAndInvalidatesToken(): void
    {
        $user = $this->createUser();
        $user->setResetPasswordTokenHash(hash('sha256', bin2hex(random_bytes(32))))
            ->setResetPasswordExpiresAt(new \DateTimeImmutable('+30 minutes'));

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, 'NovaClauSegura123!')
            ->willReturn('$2y$13$newhash');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $auditLogger = $this->createMock(UserActivityLogger::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with($user, UserActivityAction::PASSWORD_CHANGED, $this->anything(), ['context' => 'password_reset_recovery']);

        $service = new PasswordResetService(
            $this->createMock(UserRepository::class),
            $em,
            $hasher,
            $this->createMock(MailerInterface::class),
            $this->createMock(UrlGeneratorInterface::class),
            $auditLogger
        );

        $service->completeReset($user, 'NovaClauSegura123!');

        $this->assertSame('$2y$13$newhash', $user->getPassword());
        $this->assertNull($user->getResetPasswordTokenHash());
        $this->assertNull($user->getResetPasswordExpiresAt());
        $this->assertFalse($user->isResetPasswordTokenValid());
    }
}

