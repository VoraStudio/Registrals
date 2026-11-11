<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\LegacyBeneficiaryClaimController;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Repository\LegacyContactRepository;
use App\Repository\SuccessionClaimRepository;
use App\Service\Legacy\SuccessionClaimCheckoutService;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class LegacyBeneficiaryClaimControllerTest extends TestCase
{
    public function testShowDeniesNonDesignatedUserWithNotFound(): void
    {
        $owner = $this->createUser('titular@registrals.cat', 'Carles', 'Test', 1);
        $stranger = $this->createUser('intruder@exemple.cat', 'Algu', 'Extern', 99);
        $heir = $this->createUser('albert@exemple.cat', 'Albert', 'Soler', 25);
        $contact = (new LegacyContact($owner, 'Albert Soler', 'albert@exemple.cat'))
            ->accept($heir, '127.0.0.1');

        $contactRepo = $this->createMock(LegacyContactRepository::class);
        $contactRepo->method('findOneBy')->willReturn($contact);

        $controller = new LegacyBeneficiaryClaimController(
            $contactRepo,
            $this->createMock(SuccessionClaimRepository::class),
            $this->createMock(SuccessionClaimWorkflowService::class),
            $this->createMock(SuccessionClaimCheckoutService::class),
        );
        $controller->setContainer($this->createContainer($stranger));

        $this->expectException(NotFoundHttpException::class);
        $controller->show($contact->getUuid()->toRfc4122(), new Request());
    }

    public function testShowRendersBeneficiaryPortalWithoutDisclosingAssetsOrCertificates(): void
    {
        $ownerPlan = (new Plan('plus', 'Pla Plus', 2200))
            ->setAllowLegacyDesignation(true)
            ->setLegacyClaimOpeningFeeCents(2300);
        $owner = $this->createUser('titular@registrals.cat', 'Carles', 'Test', 1)->setPlan($ownerPlan);
        $heir = $this->createUser('albert@exemple.cat', 'Albert', 'Soler', 25)->setTaxId('44556677K');
        $contact = (new LegacyContact($owner, 'Albert Soler', 'albert@exemple.cat'))
            ->accept($heir, '127.0.0.1');

        $contactRepo = $this->createMock(LegacyContactRepository::class);
        $contactRepo->method('findOneBy')->willReturn($contact);

        $claimRepo = $this->createMock(SuccessionClaimRepository::class);
        $claimRepo->method('findLatestByLegacyContact')->willReturn(null);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $template, array $params) use ($heir, $contact): string {
                $this->assertSame('legacy/beneficiary_claim_portal.html.twig', $template);
                $this->assertSame($heir, $params['user']);
                $this->assertSame($contact, $params['contact']);
                $this->assertSame(2300, $params['openingFeeCents']);
                $this->assertSame('44556677K', $params['prefillTaxId']);
                $this->assertArrayNotHasKey('itemsCount', $params);
                $this->assertArrayNotHasKey('certificate', $params);

                return '<html>portal</html>';
            });

        $controller = new LegacyBeneficiaryClaimController(
            $contactRepo,
            $claimRepo,
            $this->createMock(SuccessionClaimWorkflowService::class),
            $this->createMock(SuccessionClaimCheckoutService::class),
        );
        $controller->setContainer($this->createContainer($heir, $twig));

        $response = $controller->show($contact->getUuid()->toRfc4122(), new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testCheckoutDelegatesToSharedCheckoutServiceAndRedirects(): void
    {
        $owner = $this->createUser('titular@registrals.cat', 'Carles', 'Test', 1);
        $heir = $this->createUser('albert@exemple.cat', 'Albert', 'Soler', 25);
        $contact = (new LegacyContact($owner, 'Albert Soler', 'albert@exemple.cat'))
            ->accept($heir, '127.0.0.1');
        $uuid = $contact->getUuid()->toRfc4122();

        $claim = new SuccessionClaim(
            'LEG-2026-000501',
            null,
            $owner,
            'Albert Soler',
            'albert@exemple.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000501/d.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000501/t.pdf',
            str_repeat('2', 64),
            $contact,
            $heir,
        );

        $contactRepo = $this->createMock(LegacyContactRepository::class);
        $contactRepo->method('findOneBy')->willReturn($contact);

        $claimRepo = $this->createMock(SuccessionClaimRepository::class);
        $claimRepo->method('findLatestByLegacyContact')->willReturn($claim);

        $checkoutService = $this->createMock(SuccessionClaimCheckoutService::class);
        $checkoutService->expects($this->once())
            ->method('startOpeningFeeCheckout')
            ->with($claim, $this->anything(), $this->anything())
            ->willReturn('/app/perfil/herencia/' . $uuid . '/expedient/retorn?mock=1');

        $request = new Request([], ['_token' => 'ok']);
        $request->setMethod('POST');

        $controller = new LegacyBeneficiaryClaimController(
            $contactRepo,
            $claimRepo,
            $this->createMock(SuccessionClaimWorkflowService::class),
            $checkoutService,
        );
        $controller->setContainer($this->createContainer($heir, null, $request));

        $response = $controller->checkoutOpeningFee($uuid, $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('mock=1', $response->getTargetUrl());
    }

    private function createUser(string $email, string $firstName, string $lastName, int $id): User
    {
        $user = (new User())->setEmail($email)->setFirstName($firstName)->setLastName($lastName);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }

    private function createContainer(User $actor, ?Environment $twig = null, ?Request $request = null): ContainerInterface
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($actor, 'main', $actor->getRoles()));

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://registrals.cat/app/perfil/herencia/mock/expedient');

        $req = $request ?? new Request();
        $req->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($req);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => in_array($id, [
            'security.token_storage',
            'security.csrf.token_manager',
            'router',
            'request_stack',
            'twig',
        ], true) && ($id !== 'twig' || $twig !== null));
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'security.token_storage' => $tokenStorage,
            'security.csrf.token_manager' => $csrf,
            'router' => $router,
            'request_stack' => $stack,
            'twig' => $twig,
            default => null,
        });

        return $container;
    }
}

