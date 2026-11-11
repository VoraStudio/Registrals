<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Controller\ProfileController;
use App\Entity\Plan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class ProfileControllerTest extends TestCase
{
    public function testIndexRendersProfile(): void
    {
        $plan = new Plan('Plus', 'plus', 2200, 100, 5, 5, 100);
        $user = new User('test@registrals.com', 'hashed_pass', 'Carles', 'Test', $plan);
        $user->setStripeCustomerId('cus_test123');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);
        $form->method('createView')->willReturn($this->createMock(\Symfony\Component\Form\FormView::class));
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('profile/index.html.twig', $this->callback(function (array $context) use ($user): bool {
                $this->assertSame($user, $context['user']);
                $this->assertArrayHasKey('profileForm', $context);
                $this->assertArrayHasKey('isOAuthUser', $context);
                return true;
            }))
            ->willReturn('<html>Profile rendered</html>');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => in_array($id, ['security.token_storage', 'form.factory', 'twig'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $formFactory, $twig) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'form.factory' => $formFactory,
                'twig' => $twig,
                default => null,
            };
        });

        $controller = new ProfileController();
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->index($request, $entityManager, $passwordHasher);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<html>Profile rendered</html>', $response->getContent());
    }

    public function testToggleTwoFactorEnablesAndDisablesForLocalAccount(): void
    {
        $user = new User();
        $user->setEmail('local@registrals.com');
        $user->setAuthProvider('local');
        self::assertFalse($user->isTwoFactorEnabled());

        $request = new Request([], ['_token' => 'csrf_ok']);
        $request->setMethod('POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => in_array($id, ['security.token_storage', 'security.csrf.token_manager', 'request_stack', 'router'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $csrf, $requestStack) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'security.csrf.token_manager' => $csrf,
                'request_stack' => $requestStack,
                'router' => new class {
                    public function generate(string $route): string {
                        return '/' . $route;
                    }
                },
                default => null,
            };
        });

        $controller = new ProfileController();
        $controller->setContainer($container);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))->method('flush');

        $activityLogger = $this->createMock(UserActivityLogger::class);
        $activityLogger->expects($this->exactly(2))
            ->method('log')
            ->with($user, UserActivityAction::TWO_FACTOR_TOGGLED, $this->anything(), $this->anything());

        $responseEnable = $controller->toggleTwoFactor($request, $entityManager, $activityLogger);
        self::assertInstanceOf(RedirectResponse::class, $responseEnable);
        self::assertSame('/app_profile', $responseEnable->getTargetUrl());
        self::assertTrue($user->isTwoFactorEnabled());

        $responseDisable = $controller->toggleTwoFactor($request, $entityManager, $activityLogger);
        self::assertInstanceOf(RedirectResponse::class, $responseDisable);
        self::assertFalse($user->isTwoFactorEnabled());
    }

    public function testToggleTwoFactorRejectsOAuthUsers(): void
    {
        $user = new User();
        $user->setEmail('google@registrals.com');
        $user->setAuthProvider('google');

        $request = new Request([], ['_token' => 'csrf_ok']);
        $request->setMethod('POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => in_array($id, ['security.token_storage', 'request_stack', 'router'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $requestStack) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'request_stack' => $requestStack,
                'router' => new class {
                    public function generate(string $route): string {
                        return '/' . $route;
                    }
                },
                default => null,
            };
        });

        $controller = new ProfileController();
        $controller->setContainer($container);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $response = $controller->toggleTwoFactor($request, $entityManager);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertFalse($user->isTwoFactorEnabled());
    }
}
