<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\EmailSecurityChallengeSubscriber;
use App\Security\Exception\PendingEmailVerificationException;
use App\Security\Exception\PendingTwoFactorAuthenticationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

class EmailSecurityChallengeSubscriberTest extends TestCase
{
    public function testIgnoresNonPasswordPassportsSuchAsOAuthOrPostOtpLogin(): void
    {
        $user = new User();
        $user->setEmail('google@registrals.com');
        $user->setAuthProvider('local');
        $user->setTwoFactorEnabled(true);

        $passport = new SelfValidatingPassport(
            new UserBadge($user->getEmail(), fn () => $user),
            [new PreAuthenticatedUserBadge()]
        );

        $subscriber = new EmailSecurityChallengeSubscriber();
        $subscriber->onCheckPassport(new CheckPassportEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport
        ));

        self::assertTrue(true);
    }

    public function testThrowsPendingEmailVerificationWhenLocalUserUnverified(): void
    {
        $user = new User();
        $user->setEmail('nou@registrals.com');
        $user->setAuthProvider('local');

        $passport = new Passport(
            new UserBadge($user->getEmail(), fn () => $user),
            new PasswordCredentials('secret123')
        );

        $subscriber = new EmailSecurityChallengeSubscriber();

        $this->expectException(PendingEmailVerificationException::class);
        $subscriber->onCheckPassport(new CheckPassportEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport
        ));
    }

    public function testThrowsPendingTwoFactorWhenVerifiedLocalUserHas2faEnabled(): void
    {
        $user = new User();
        $user->setEmail('segur@registrals.com');
        $user->setAuthProvider('local');
        $user->markEmailVerified();
        $user->setTwoFactorEnabled(true);

        $passport = new Passport(
            new UserBadge($user->getEmail(), fn () => $user),
            new PasswordCredentials('secret123')
        );

        $subscriber = new EmailSecurityChallengeSubscriber();

        $this->expectException(PendingTwoFactorAuthenticationException::class);
        $subscriber->onCheckPassport(new CheckPassportEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport
        ));
    }

    public function testAllowsVerifiedLocalUserWith2faDisabledByDefault(): void
    {
        $user = new User();
        $user->setEmail('normal@registrals.com');
        $user->setAuthProvider('local');
        $user->markEmailVerified();

        $passport = new Passport(
            new UserBadge($user->getEmail(), fn () => $user),
            new PasswordCredentials('secret123')
        );

        $subscriber = new EmailSecurityChallengeSubscriber();
        $subscriber->onCheckPassport(new CheckPassportEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport
        ));

        self::assertFalse($user->isTwoFactorEnabled());
    }
}
