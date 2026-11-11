<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Controller\LegacyInvitationController;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\EmailOtpPurpose;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use App\Service\Legacy\LegacyDesignationService;
use App\Service\Security\AuthChallengeStorage;
use App\Service\Security\EmailOtpService;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class LegacyInvitationControllerTest extends TestCase
{
    private function createController(
        LegacyDesignationService $legacyService,
        ?UserRepository $userRepo = null,
        ?PlanRepository $planRepo = null,
        ?EntityManagerInterface $em = null,
        ?UserPasswordHasherInterface $passwordHasher = null,
        ?EmailOtpService $otpService = null,
        ?AuthChallengeStorage $challengeStorage = null,
        ?UserActivityLogger $auditLogger = null,
        ?User $authenticatedUser = null,
        ?Environment $twig = null,
        ?FormFactoryInterface $formFactory = null,
        ?CsrfTokenManagerInterface $csrf = null,
        ?RequestStack $requestStack = null,
    ): LegacyInvitationController {
        $controller = new LegacyInvitationController(
            $legacyService,
            $userRepo ?? $this->createMock(UserRepository::class),
            $planRepo ?? $this->createMock(PlanRepository::class),
            $em ?? $this->createMock(EntityManagerInterface::class),
            $passwordHasher ?? $this->createMock(UserPasswordHasherInterface::class),
            $otpService ?? $this->createMock(EmailOtpService::class),
            $challengeStorage ?? $this->createMock(AuthChallengeStorage::class),
            $auditLogger,
        );

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(
            $authenticatedUser !== null
                ? new UsernamePasswordToken($authenticatedUser, 'main', $authenticatedUser->getRoles())
                : null
        );

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => in_array($id, [
            'security.token_storage',
            'twig',
            'form.factory',
            'security.csrf.token_manager',
            'request_stack',
            'router',
        ], true));
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'security.token_storage' => $tokenStorage,
            'twig' => $twig,
            'form.factory' => $formFactory,
            'security.csrf.token_manager' => $csrf,
            'request_stack' => $requestStack,
            'router' => new class {
                public function generate(string $route, array $params = []): string {
                    return '/' . $route;
                }
            },
            default => null,
        });

        $controller->setContainer($container);

        return $controller;
    }

    public function testSignupForUnregisteredContactCreatesFreeAccountSendsOtpAndStoresPendingToken(): void
    {
        $rawToken = str_repeat('b', 64);
        $owner = (new User())->setEmail('owner@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');
        $contact->issueInvitationToken(hash('sha256', $rawToken), new \DateTimeImmutable('+7 days'));

        $freePlan = new Plan('free', 'Pla Free', 0);

        $legacyService = $this->createMock(LegacyDesignationService::class);
        $legacyService->method('resolveInvitationByRawToken')->with($rawToken)->willReturn([
            'contact' => $contact,
            'state' => 'valid',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->with(['email' => 'maria@exemple.cat'])->willReturn(null);

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findDefaultFreePlan')->willReturn($freePlan);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->callback(function (User $newUser): bool {
            $this->assertSame('maria@exemple.cat', $newUser->getEmail());
            $this->assertSame('Maria', $newUser->getFirstName());
            $this->assertSame('Puig', $newUser->getLastName());

            $ref = new \ReflectionProperty(User::class, 'id');
            $ref->setValue($newUser, 77);

            return true;
        }));
        $em->expects($this->once())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->method('hashPassword')->willReturn('hashed_pw');

        $fieldFirst = $this->createMock(FormInterface::class);
        $fieldFirst->method('getData')->willReturn('Maria');
        $fieldLast = $this->createMock(FormInterface::class);
        $fieldLast->method('getData')->willReturn('Puig');
        $fieldPass = $this->createMock(FormInterface::class);
        $fieldPass->method('getData')->willReturn('Secret1234!');

        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->willReturnCallback(fn (string $name) => match ($name) {
            'firstName' => $fieldFirst,
            'lastName' => $fieldLast,
            'plainPassword' => $fieldPass,
            default => $this->createMock(FormInterface::class),
        });

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $otpService = $this->createMock(EmailOtpService::class);
        $otpService->expects($this->once())->method('issueOtp')->with($this->isInstanceOf(User::class), EmailOtpPurpose::REGISTRATION_VERIFY)
            ->willReturn(['sent' => true, 'cooldownRemaining' => 60, 'error' => null]);

        $challengeStorage = $this->createMock(AuthChallengeStorage::class);
        $challengeStorage->expects($this->once())->method('store');

        $auditLogger = $this->createMock(UserActivityLogger::class);
        $auditLogger->expects($this->once())->method('log')->with(
            $this->isInstanceOf(User::class),
            UserActivityAction::REGISTER,
            $this->anything(),
            $this->callback(fn (array $details): bool => ($details['source'] ?? null) === 'legacy_invitation')
        );

        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/llegat/acceptar/' . $rawToken . '/registrar', 'POST');
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $controller = $this->createController(
            legacyService: $legacyService,
            userRepo: $userRepo,
            planRepo: $planRepo,
            em: $em,
            passwordHasher: $passwordHasher,
            otpService: $otpService,
            challengeStorage: $challengeStorage,
            auditLogger: $auditLogger,
            formFactory: $formFactory,
            requestStack: $requestStack,
        );

        $response = $controller->signup($rawToken, $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app_register_verify_email', $response->getTargetUrl());
        $this->assertSame($rawToken, $session->get('pending_legacy_accept_token'));
    }

    public function testShowRendersSignupFormWhenRecipientHasNoAccount(): void
    {
        $rawToken = str_repeat('c', 64);
        $owner = (new User())->setEmail('owner@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $contact = new LegacyContact($owner, 'Maria Puig Serra', 'maria@exemple.cat');
        $contact->issueInvitationToken(hash('sha256', $rawToken), new \DateTimeImmutable('+7 days'));

        $legacyService = $this->createMock(LegacyDesignationService::class);
        $legacyService->method('resolveInvitationByRawToken')->willReturn([
            'contact' => $contact,
            'state' => 'valid',
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn($this->createMock(FormView::class));
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->with(
            'legacy/invitation.html.twig',
            $this->callback(fn (array $ctx): bool => $ctx['accountExistsForContact'] === false && $ctx['signupForm'] !== null)
        )->willReturn('<html>ok</html>');

        $controller = $this->createController(
            legacyService: $legacyService,
            userRepo: $userRepo,
            twig: $twig,
            formFactory: $formFactory,
        );

        $response = $controller->show($rawToken);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
