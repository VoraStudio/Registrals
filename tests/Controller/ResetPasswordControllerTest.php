<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ResetPasswordController;
use App\Entity\User;
use App\Service\Security\PasswordResetService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

class ResetPasswordControllerTest extends TestCase
{
    private function createContainer(
        ?User $user = null,
        ?Environment $twig = null,
        ?UrlGeneratorInterface $router = null,
        ?FormFactoryInterface $formFactory = null,
        ?Session $session = null
    ): ContainerInterface {
        $services = [];
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(
            $user !== null ? new UsernamePasswordToken($user, 'main', $user->getRoles()) : null
        );
        $services['security.token_storage'] = $tokenStorage;

        if ($twig !== null) {
            $services['twig'] = $twig;
        }
        if ($router !== null) {
            $services['router'] = $router;
        }
        if ($formFactory !== null) {
            $services['form.factory'] = $formFactory;
        }
        if ($session !== null) {
            $requestStack = new RequestStack();
            $request = new Request();
            $request->setSession($session);
            $requestStack->push($request);
            $services['request_stack'] = $requestStack;
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(fn (string $id) => $services[$id] ?? null);

        return $container;
    }

    public function testForgotPasswordRedirectsAuthenticatedUsersToProfile(): void
    {
        $user = new User();
        $user->setEmail('client@registrals.cat');

        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_profile')->willReturn('/app/perfil');

        $controller = new ResetPasswordController();
        $controller->setContainer($this->createContainer($user, router: $router, session: $session));

        $response = $controller->request(new Request(), $this->createMock(PasswordResetService::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app/perfil', $response->getTargetUrl());
    }

    public function testForgotPasswordValidSubmitInvokesServiceAndShowsNeutralConfirmation(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_forgot_password', ['sent' => 1])->willReturn('/forgot-password?sent=1');

        $emailField = $this->createMock(FormInterface::class);
        $emailField->method('getData')->willReturn('client@registrals.cat');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('email')->willReturn($emailField);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $resetService = $this->createMock(PasswordResetService::class);
        $resetService->expects($this->once())
            ->method('requestReset')
            ->with('client@registrals.cat');

        $controller = new ResetPasswordController();
        $controller->setContainer($this->createContainer(router: $router, formFactory: $formFactory, session: $session));

        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);
        $response = $controller->request($request, $resetService);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/forgot-password?sent=1', $response->getTargetUrl());
        $this->assertNotEmpty($session->getFlashBag()->get('success'));
    }

    public function testResetPasswordRejectsInvalidOrExpiredToken(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_forgot_password')->willReturn('/forgot-password');

        $resetService = $this->createMock(PasswordResetService::class);
        $resetService->expects($this->once())
            ->method('findValidUserByRawToken')
            ->with('badtoken')
            ->willReturn(null);

        $controller = new ResetPasswordController();
        $controller->setContainer($this->createContainer(router: $router, session: $session));

        $response = $controller->reset('badtoken', new Request(), $resetService);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/forgot-password', $response->getTargetUrl());
        $this->assertNotEmpty($session->getFlashBag()->get('error'));
    }

    public function testResetPasswordGetSetsNoReferrerAndNoStoreHeaders(): void
    {
        $user = new User();
        $user->setEmail('client@registrals.cat');

        $rawToken = bin2hex(random_bytes(32));
        $resetService = $this->createMock(PasswordResetService::class);
        $resetService->method('findValidUserByRawToken')->with($rawToken)->willReturn($user);

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(false);
        $form->method('createView')->willReturn(new FormView());

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('security/reset_password.html.twig', $this->arrayHasKey('resetForm'))
            ->willReturn('<html>Reset</html>');

        $controller = new ResetPasswordController();
        $controller->setContainer($this->createContainer(twig: $twig, formFactory: $formFactory));

        $response = $controller->reset($rawToken, new Request(), $resetService);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testResetPasswordValidSubmitCompletesResetAndRedirectsToLogin(): void
    {
        $user = new User();
        $user->setEmail('client@registrals.cat');

        $rawToken = bin2hex(random_bytes(32));
        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_login')->willReturn('/login');

        $passwordField = $this->createMock(FormInterface::class);
        $passwordField->method('getData')->willReturn('NovaClau2026!');

        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('newPassword')->willReturn($passwordField);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $resetService = $this->createMock(PasswordResetService::class);
        $resetService->method('findValidUserByRawToken')->with($rawToken)->willReturn($user);
        $resetService->expects($this->once())
            ->method('completeReset')
            ->with($user, 'NovaClau2026!');

        $controller = new ResetPasswordController();
        $controller->setContainer($this->createContainer(router: $router, formFactory: $formFactory, session: $session));

        $request = new Request([], [], [], [], [], ['REQUEST_METHOD' => 'POST']);
        $response = $controller->reset($rawToken, $request, $resetService);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/login', $response->getTargetUrl());
        $this->assertNotEmpty($session->getFlashBag()->get('success'));
    }
}
