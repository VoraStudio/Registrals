<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\SecurityChallengeController;
use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Enum\EmailOtpVerificationStatus;
use App\Repository\UserRepository;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Twig\Environment;

class SecurityChallengeControllerTest extends TestCase
{
    private function createController(
        EmailOtpService $emailOtpService,
        AuthChallengeStorage $challengeStorage,
        UserRepository $userRepository,
        Security $security,
        ?FormFactoryInterface $formFactory = null,
        ?Environment $twig = null,
        ?RequestStack $requestStack = null,
        ?CsrfTokenManagerInterface $csrf = null,
        ?User $authenticatedUser = null
    ): SecurityChallengeController {
        $controller = new SecurityChallengeController($emailOtpService, $challengeStorage, $userRepository, $security);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        if ($authenticatedUser !== null) {
            $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
            $token->method('getUser')->willReturn($authenticatedUser);
            $tokenStorage->method('getToken')->willReturn($token);
        } else {
            $tokenStorage->method('getToken')->willReturn(null);
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => in_array($id, [
            'security.token_storage',
            'form.factory',
            'twig',
            'request_stack',
            'security.csrf.token_manager',
            'router',
        ], true));
        $container->method('get')->willReturnCallback(function (string $id) use (
            $tokenStorage,
            $formFactory,
            $twig,
            $requestStack,
            $csrf
        ) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'form.factory' => $formFactory,
                'twig' => $twig,
                'request_stack' => $requestStack,
                'security.csrf.token_manager' => $csrf,
                'router' => new class {
                    public function generate(string $route, array $params = []): string {
                        return '/' . $route;
                    }
                },
                default => null,
            };
        });

        $controller->setContainer($container);

        return $controller;
    }

    private function createUser(int $id = 12): User
    {
        $user = new User();
        $user->setEmail('cmellado@institutmontilivi.cat');
        $user->setFirstName('Carles');
        $user->setLastName('Mellado');
        $user->setAuthProvider('local');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }

    public function testRedirectsToLoginWhenNoChallengeSessionPresent(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $controller = $this->createController(
            $this->createMock(EmailOtpService::class),
            new AuthChallengeStorage(),
            $this->createMock(UserRepository::class),
            $this->createMock(Security::class),
            requestStack: $requestStack
        );

        $response = $controller->verifyLoginTwoFactor($request);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/app_login', $response->getTargetUrl());
    }

    public function testValidRegistrationOtpLogsInUserAndRoutesToLegalGatedDashboard(): void
    {
        $user = $this->createUser(44);
        $challengeStorage = new AuthChallengeStorage();

        $request = new Request();
        $request->setMethod('POST');
        $session = new Session(new MockArraySessionStorage());
        $challengeStorage->store($session, 44, EmailOtpPurpose::REGISTRATION_VERIFY, false);
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with(44)->willReturn($user);

        $emailOtpService = $this->createMock(EmailOtpService::class);
        $emailOtpService->expects($this->once())
            ->method('verifyOtp')
            ->with($user, EmailOtpPurpose::REGISTRATION_VERIFY, '654321')
            ->willReturn(EmailOtpVerificationStatus::VALID);

        $codeField = $this->createMock(FormInterface::class);
        $codeField->method('getData')->willReturn('654321');
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('code')->willReturn($codeField);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('login')
            ->with($user, 'form_login', 'main', []);

        $controller = $this->createController(
            $emailOtpService,
            $challengeStorage,
            $userRepo,
            $security,
            formFactory: $formFactory,
            requestStack: $requestStack
        );

        $response = $controller->verifyRegistrationEmail($request);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/app_dashboard', $response->getTargetUrl());
        self::assertNull($challengeStorage->get($session, EmailOtpPurpose::REGISTRATION_VERIFY));
    }

    public function testValidLogin2faWithRememberMePassesEnabledRememberMeBadge(): void
    {
        $user = $this->createUser(55);
        $user->markEmailVerified()->setTwoFactorEnabled(true);
        $challengeStorage = new AuthChallengeStorage();

        $request = new Request();
        $request->setMethod('POST');
        $session = new Session(new MockArraySessionStorage());
        $challengeStorage->store($session, 55, EmailOtpPurpose::LOGIN_2FA, true);
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with(55)->willReturn($user);

        $emailOtpService = $this->createMock(EmailOtpService::class);
        $emailOtpService->expects($this->once())
            ->method('verifyOtp')
            ->with($user, EmailOtpPurpose::LOGIN_2FA, '112233')
            ->willReturn(EmailOtpVerificationStatus::VALID);

        $codeField = $this->createMock(FormInterface::class);
        $codeField->method('getData')->willReturn('112233');
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('code')->willReturn($codeField);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $security = $this->createMock(Security::class);
        $security->expects($this->once())
            ->method('login')
            ->with(
                $user,
                'form_login',
                'main',
                self::callback(function (array $badges): bool {
                    return count($badges) === 1
                        && $badges[0] instanceof RememberMeBadge
                        && $badges[0]->isEnabled();
                })
            )
            ->willReturn(new RedirectResponse('/app/dashboard'));

        $controller = $this->createController(
            $emailOtpService,
            $challengeStorage,
            $userRepo,
            $security,
            formFactory: $formFactory,
            requestStack: $requestStack
        );

        $response = $controller->verifyLoginTwoFactor($request);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/app/dashboard', $response->getTargetUrl());
    }

    public function testResendWithValidCsrfCallsIssueOtpWithCooldown(): void
    {
        $user = $this->createUser(88);
        $user->markEmailVerified()->setTwoFactorEnabled(true);
        $challengeStorage = new AuthChallengeStorage();

        $request = new Request([], ['_token' => 'ok']);
        $request->setMethod('POST');
        $session = new Session(new MockArraySessionStorage());
        $challengeStorage->store($session, 88, EmailOtpPurpose::LOGIN_2FA, false);
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('find')->with(88)->willReturn($user);

        $emailOtpService = $this->createMock(EmailOtpService::class);
        $emailOtpService->expects($this->once())
            ->method('issueOtp')
            ->with($user, EmailOtpPurpose::LOGIN_2FA, true)
            ->willReturn(['sent' => true, 'cooldownRemaining' => 60, 'error' => null]);

        $controller = $this->createController(
            $emailOtpService,
            $challengeStorage,
            $userRepo,
            $this->createMock(Security::class),
            requestStack: $requestStack,
            csrf: $csrf
        );

        $response = $controller->resendLoginTwoFactor($request);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/app_login_2fa', $response->getTargetUrl());
    }
}
