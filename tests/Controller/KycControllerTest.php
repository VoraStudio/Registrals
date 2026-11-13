<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\KycController;
use App\DTO\KycSessionDto;
use App\DTO\KycVerdictDto;
use App\Entity\User;
use App\Enum\KycStatus;
use App\Service\Identity\KycVerificationService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class KycControllerTest extends TestCase
{
    private function setupController(KycController $controller, User $user, ?Request $request = null, bool $csrfValid = true): void
    {
        $session = new Session(new MockArraySessionStorage());
        $stack = new RequestStack();
        $req = $request ?? new Request();
        $req->setSession($session);
        $stack->push($req);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(fn (string $name) => match ($name) {
            'app_kyc_callback' => 'https://registrals.cat/app/perfil/verificacio/retorn',
            default => '/app/perfil',
        });

        $container = $this->createMock(ContainerInterface::class);
        $services = [
            'request_stack' => $stack,
            'security.token_storage' => $tokenStorage,
            'security.csrf.token_manager' => $csrf,
            'router' => $router,
        ];
        $container->method('has')->willReturnCallback(fn (string $id) => array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(fn (string $id) => $services[$id] ?? null);

        $controller->setContainer($container);
    }

    public function testStartRedirectsToNamirialWizardUrl(): void
    {
        $user = (new User())->setEmail('plus@registrals.cat')->setFirstName('Carles')->setLastName('Mellado');

        $kycService = $this->createMock(KycVerificationService::class);
        $kycService->method('canStartWithoutCheckout')->with($user)->willReturn(true);
        $kycService->expects($this->once())
            ->method('initiateVerification')
            ->with(
                $user,
                'https://registrals.cat/app/perfil/verificacio/retorn?requestId=<requestId>&referenceId=<referenceId>&status=<status>&actor=<actor>',
                $this->anything()
            )
            ->willReturn(new KycSessionDto('req-1', 'REG-KYC-1', 'https://wizard.namirialonboarding.com/s/1'));

        $request = new Request([], ['_token' => 'ok']);
        $controller = new KycController($kycService);
        $this->setupController($controller, $user, $request);

        $response = $controller->start($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('https://wizard.namirialonboarding.com/s/1', $response->getTargetUrl());
    }

    public function testCallbackSyncsStatusServerSide(): void
    {
        $user = (new User())
            ->setEmail('plus@registrals.cat')
            ->setFirstName('Carles')
            ->setLastName('Mellado')
            ->setSignaturitKycId('req-sync-1');

        $kycService = $this->createMock(KycVerificationService::class);
        $kycService->expects($this->once())
            ->method('syncUserVerificationStatus')
            ->with($user, $this->anything(), 'callback')
            ->willReturn(new KycVerdictDto('req-sync-1', 'REG-1', KycStatus::APPROVED, 'COMPLETED'));

        $request = new Request(['requestId' => 'req-sync-1', 'status' => 'COMPLETED']);
        $controller = new KycController($kycService);
        $this->setupController($controller, $user, $request);

        $response = $controller->callback($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app/perfil', $response->getTargetUrl());
    }
}

