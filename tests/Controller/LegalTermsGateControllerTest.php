<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Controller\LegalTermsGateController;
use App\Entity\User;
use App\EventSubscriber\LegalGateSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class LegalTermsGateControllerTest extends TestCase
{
    private function createControllerWithUser(User $user, ?CsrfTokenManagerInterface $csrf = null, ?Environment $twig = null, ?\Symfony\Component\HttpFoundation\RequestStack $requestStack = null): LegalTermsGateController
    {
        $controller = new LegalTermsGateController();

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => in_array($id, ['security.token_storage', 'security.csrf.token_manager', 'twig', 'router', 'request_stack'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $csrf, $twig, $requestStack) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'security.csrf.token_manager' => $csrf,
                'twig' => $twig,
                'request_stack' => $requestStack,
                'router' => new class {
                    public function generate(string $route): string {
                        return '/' . $route;
                    }
                },
                default => null,
            };
        });

        $controller->setContainer($container);

        return $controller;
    }

    public function testRedirectsToDashboardIfAlreadyAccepted(): void
    {
        $user = new User();
        $user->setLegalTermsAcceptedAt(new \DateTimeImmutable());
        $user->setLegalTermsVersion(LegalGateSubscriber::CURRENT_TERMS_VERSION);

        $controller = $this->createControllerWithUser($user);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(UserActivityLogger::class);

        $request = Request::create('/app/legal/acceptar-condicions', 'GET');
        $response = $controller->acceptTerms($request, $em, $logger);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/app_dashboard', $response->getTargetUrl());
    }

    public function testRendersTemplateOnGetWhenNotAccepted(): void
    {
        $user = new User();
        $user->setFirstName('Carles');
        $user->setLastName('Vora');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('legal/accept_terms.html.twig', self::callback(function (array $params) use ($user) {
                return $params['termsVersion'] === LegalGateSubscriber::CURRENT_TERMS_VERSION
                    && $params['user'] === $user;
            }))
            ->willReturn('<html>accept terms</html>');

        $controller = $this->createControllerWithUser($user, twig: $twig);
        $em = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(UserActivityLogger::class);

        $request = Request::create('/app/legal/acceptar-condicions', 'GET');
        $response = $controller->acceptTerms($request, $em, $logger);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('<html>accept terms</html>', $response->getContent());
    }

    public function testAcceptsTermsOnPostWithValidCsrf(): void
    {
        $user = new User();
        $user->setEmail('carles@example.com');
        $user->setFirstName('Carles');
        $user->setLastName('Vora');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $request = Request::create(
            '/app/legal/acceptar-condicions',
            'POST',
            ['_token' => 'valid_token']
        );
        $session = new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
        $request->setSession($session);
        $request->server->set('REMOTE_ADDR', '192.168.1.100');

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $requestStack->push($request);

        $controller = $this->createControllerWithUser($user, csrf: $csrf, requestStack: $requestStack);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $logger = $this->createMock(UserActivityLogger::class);
        $logger->expects($this->once())
            ->method('log')
            ->with(
                $user,
                UserActivityAction::LEGAL_TERMS_ACCEPTED,
                self::stringContains('v1.0')
            );

        $response = $controller->acceptTerms($request, $em, $logger);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/app_dashboard', $response->getTargetUrl());

        self::assertTrue($user->hasAcceptedLegalTerms(LegalGateSubscriber::CURRENT_TERMS_VERSION));
        self::assertSame(LegalGateSubscriber::CURRENT_TERMS_VERSION, $user->getLegalTermsVersion());
        self::assertNotNull($user->getLegalTermsAcceptedAt());
        self::assertSame('192.168.1.100', $user->getRegistrationIp());
    }
}
