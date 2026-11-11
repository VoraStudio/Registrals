<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Audit\Service\UserActivityLogger;
use App\Controller\RegistrationController;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Form\RegistrationFormType;
use App\Repository\PlanRepository;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class RegistrationControllerTest extends TestCase
{
    private function createController(?User $currentUser, ?Environment $twig, ?FormFactoryInterface $formFactory, ?RequestStack $requestStack = null): RegistrationController
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
        $container->method('has')->willReturnCallback(fn (string $id) => in_array($id, ['security.token_storage', 'twig', 'form.factory', 'router', 'request_stack'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $twig, $formFactory, $requestStack) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'twig' => $twig,
                'form.factory' => $formFactory,
                'request_stack' => $requestStack,
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
        $emailOtpService = $this->createMock(EmailOtpService::class);
        $challengeStorage = new AuthChallengeStorage();

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $emailOtpService, $challengeStorage);
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
        $emailOtpService = $this->createMock(EmailOtpService::class);
        $challengeStorage = new AuthChallengeStorage();

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $emailOtpService, $challengeStorage);
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
        $emailOtpService = $this->createMock(EmailOtpService::class);
        $challengeStorage = new AuthChallengeStorage();

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $emailOtpService, $challengeStorage);
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
        $emailOtpService = $this->createMock(EmailOtpService::class);
        $challengeStorage = new AuthChallengeStorage();

        $response = $controller->register($request, $passwordHasher, $entityManager, $planRepository, $emailOtpService, $challengeStorage);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($session->has('intended_plan'));
    }

    public function testRegisterPostRequiresEmailOtpAndDefersLegalGateAcceptance(): void
    {
        $request = new Request([], ['plan' => 'plus']);
        $request->setMethod('POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $capturedUser = null;
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $plainPassField = $this->createMock(FormInterface::class);
        $plainPassField->method('getData')->willReturn('SecretPass123!');

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->with($request);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->with('plainPassword')->willReturn($plainPassField);

        $formFactory->method('create')
            ->willReturnCallback(function (string $type, User $user) use ($form, &$capturedUser) {
                $user->setEmail('NouUsuari@InstitutMontilivi.cat');
                $user->setFirstName('Carles');
                $user->setLastName('Mellado');
                $capturedUser = $user;
                return $form;
            });

        $plan = new Plan('Free', 'free', 0, 20, 1, 1, 20);
        $planRepository = $this->createMock(PlanRepository::class);
        $planRepository->method('findDefaultFreePlan')->willReturn($plan);

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_pw');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush')->willReturnCallback(function () use (&$capturedUser): void {
            $ref = new \ReflectionProperty(User::class, 'id');
            $ref->setValue($capturedUser, 77);
        });

        $emailOtpService = $this->createMock(EmailOtpService::class);
        $emailOtpService->expects($this->once())
            ->method('issueOtp')
            ->with($this->isInstanceOf(User::class), EmailOtpPurpose::REGISTRATION_VERIFY)
            ->willReturn(['sent' => true, 'cooldownRemaining' => 60, 'error' => null]);

        $challengeStorage = new AuthChallengeStorage();
        $activityLogger = $this->createMock(UserActivityLogger::class);
        $activityLogger->expects($this->once())->method('log');

        $controller = $this->createController(null, null, $formFactory, $requestStack);
        $response = $controller->register(
            $request,
            $passwordHasher,
            $entityManager,
            $planRepository,
            $emailOtpService,
            $challengeStorage,
            $activityLogger
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/app_register_verify_email', $response->getTargetUrl());
        self::assertNotNull($capturedUser);
        self::assertSame('nouusuari@institutmontilivi.cat', $capturedUser->getEmail());
        self::assertFalse($capturedUser->isEmailVerified());
        self::assertNull($capturedUser->getLegalTermsVersion());
        self::assertNull($capturedUser->getLegalTermsAcceptedAt());

        $challenge = $challengeStorage->get($session, EmailOtpPurpose::REGISTRATION_VERIFY);
        self::assertNotNull($challenge);
        self::assertSame(77, $challenge['user_id']);
        self::assertSame('plus', $session->get('intended_plan'));
    }
}
