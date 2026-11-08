<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\RegistrationController;
use App\Entity\Plan;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class RegistrationControllerTest extends TestCase
{
    private function createController(?User $currentUser, ?Environment $twig, ?FormFactoryInterface $formFactory): RegistrationController
    {
        $controller = new RegistrationController();

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        if ($currentUser !== null) {
            $token = $this->createMock(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class);
            $token->method('getUser')->willReturn($currentUser);
            $tokenStorage->method('getToken')->willReturn($token);
        } else {
            $tokenStorage->method('getToken')->willReturn(null);
        }

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => in_array($id, ['security.token_storage', 'twig', 'form.factory', 'router'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $twig, $formFactory) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'twig' => $twig,
                'form.factory' => $formFactory,
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

    public function testRegisterRendersFreePlanByDefault(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(RegistrationFormType::class, $this->isInstanceOf(User::class))->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('registration/register.html.twig', $this->callback(function (array $context): bool {
                $this->assertSame('free', $context['plan']);
                $this->assertArrayHasKey('registrationForm', $context);
                return true;
            }))
            ->willReturn('<html>Register Free</html>');

        $controller = $this->createController(null, $twig, $formFactory);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $planRepository = $this->createMock(PlanRepository::class);
        $security = $this->createMock(Security::class);

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $security);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($session->has('intended_plan'));
    }

    public function testRegisterRendersPlusPlanWhenParamGiven(): void
    {
        $request = new Request(['plan' => 'plus']);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(RegistrationFormType::class, $this->isInstanceOf(User::class))->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('registration/register.html.twig', $this->callback(function (array $context): bool {
                $this->assertSame('plus', $context['plan']);
                return true;
            }))
            ->willReturn('<html>Register Plus</html>');

        $controller = $this->createController(null, $twig, $formFactory);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $planRepository = $this->createMock(PlanRepository::class);
        $security = $this->createMock(Security::class);

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $security);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($session->has('intended_plan'));
        $this->assertSame('plus', $session->get('intended_plan'));
    }

    public function testRegisterClearsIntendedPlanWhenParamFree(): void
    {
        $request = new Request(['plan' => 'free']);
        $session = new Session(new MockArraySessionStorage());
        $session->set('intended_plan', 'plus');
        $request->setSession($session);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(RegistrationFormType::class, $this->isInstanceOf(User::class))->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('registration/register.html.twig', $this->callback(function (array $context): bool {
                $this->assertSame('free', $context['plan']);
                return true;
            }))
            ->willReturn('<html>Register Free</html>');

        $controller = $this->createController(null, $twig, $formFactory);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $planRepository = $this->createMock(PlanRepository::class);
        $security = $this->createMock(Security::class);

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $security);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($session->has('intended_plan'));
    }

    public function testRegisterGetWithoutParamAlwaysDefaultsToFreeAndClearsSession(): void
    {
        $request = new Request();
        $session = new Session(new MockArraySessionStorage());
        $session->set('intended_plan', 'plus');
        $request->setSession($session);

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(false);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->with(RegistrationFormType::class, $this->isInstanceOf(User::class))->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('registration/register.html.twig', $this->callback(function (array $context): bool {
                $this->assertSame('free', $context['plan']);
                return true;
            }))
            ->willReturn('<html>Register Free</html>');

        $controller = $this->createController(null, $twig, $formFactory);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $planRepository = $this->createMock(PlanRepository::class);
        $security = $this->createMock(Security::class);

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $security);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($session->has('intended_plan'));
    }
}
