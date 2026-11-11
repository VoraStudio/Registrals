<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Security\Exception\PendingEmailVerificationException;
use App\Security\Exception\PendingTwoFactorAuthenticationException;
use App\Security\LoginAuthenticationFailureHandler;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\HttpUtils;

class LoginAuthenticationFailureHandlerTest extends TestCase
{
    public function testPendingEmailVerificationRedirectsToVerificationChallenge(): void
    {
        $user = new User();
        $user->setEmail('pendent@registrals.com');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 21);

        $otpService = $this->createMock(EmailOtpService::class);
        $otpService->expects($this->once())
            ->method('issueOtp')
            ->with($user, EmailOtpPurpose::REGISTRATION_VERIFY, true)
            ->willReturn(['sent' => true, 'cooldownRemaining' => 60, 'error' => null]);

        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $urlGen->expects($this->once())
            ->method('generate')
            ->with('app_register_verify_email')
            ->willReturn('/register/verify-email');

        $challengeStorage = new AuthChallengeStorage();
        $handler = new LoginAuthenticationFailureHandler(
            $this->createMock(HttpKernelInterface::class),
            $this->createMock(HttpUtils::class),
            $otpService,
            $challengeStorage,
            $urlGen
        );

        $request = new Request([], ['_remember_me' => 'on']);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $handler->onAuthenticationFailure($request, new PendingEmailVerificationException($user));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/register/verify-email', $response->getTargetUrl());

        $challenge = $challengeStorage->get($session, EmailOtpPurpose::REGISTRATION_VERIFY);
        self::assertNotNull($challenge);
        self::assertSame(21, $challenge['user_id']);
        self::assertTrue($challenge['remember_me']);
    }

    public function testPendingTwoFactorRedirectsToLogin2faChallenge(): void
    {
        $user = new User();
        $user->setEmail('2fa@registrals.com');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, 34);

        $otpService = $this->createMock(EmailOtpService::class);
        $otpService->expects($this->once())
            ->method('issueOtp')
            ->with($user, EmailOtpPurpose::LOGIN_2FA, false)
            ->willReturn(['sent' => true, 'cooldownRemaining' => 60, 'error' => null]);

        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $urlGen->expects($this->once())
            ->method('generate')
            ->with('app_login_2fa')
            ->willReturn('/login/2fa');

        $challengeStorage = new AuthChallengeStorage();
        $handler = new LoginAuthenticationFailureHandler(
            $this->createMock(HttpKernelInterface::class),
            $this->createMock(HttpUtils::class),
            $otpService,
            $challengeStorage,
            $urlGen
        );

        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $handler->onAuthenticationFailure($request, new PendingTwoFactorAuthenticationException($user));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login/2fa', $response->getTargetUrl());

        $challenge = $challengeStorage->get($session, EmailOtpPurpose::LOGIN_2FA);
        self::assertNotNull($challenge);
        self::assertSame(34, $challenge['user_id']);
    }

    public function testStandardBadCredentialsDelegatesToDefaultHandler(): void
    {
        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('createRedirectResponse')
            ->willReturn(new RedirectResponse('/login'));

        $otpService = $this->createMock(EmailOtpService::class);
        $otpService->expects($this->never())->method('issueOtp');

        $handler = new LoginAuthenticationFailureHandler(
            $this->createMock(HttpKernelInterface::class),
            $httpUtils,
            $otpService,
            new AuthChallengeStorage(),
            $this->createMock(UrlGeneratorInterface::class)
        );

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $response = $handler->onAuthenticationFailure($request, new BadCredentialsException());
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->getTargetUrl());
    }
}
