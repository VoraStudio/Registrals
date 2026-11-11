<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\LegacyProfileController;
use App\Entity\LegacyContact;
use App\Entity\User;
use App\Repository\LegacyContactRepository;
use App\Service\Legacy\LegacyDesignationService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class LegacyProfileControllerTest extends TestCase
{
    public function testRevokeRedirectsToProfileAnchorWithFlash(): void
    {
        $owner = (new User())->setEmail('titular@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $refOwner = new \ReflectionProperty(User::class, 'id');
        $refOwner->setValue($owner, 42);

        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');
        $uuid = $contact->getUuid()->toRfc4122();

        $repo = $this->createMock(LegacyContactRepository::class);
        $repo->method('findOneBy')->willReturn($contact);

        $legacyService = $this->createMock(LegacyDesignationService::class);
        $legacyService->expects($this->once())->method('revoke')->with($owner, $contact)->willReturn(1);

        $request = new Request([], ['_token' => 'csrf_ok']);
        $request->setMethod('POST');
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn(true);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($owner, 'main', $owner->getRoles()));

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => in_array($id, ['security.token_storage', 'security.csrf.token_manager', 'request_stack', 'router'], true));
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'security.token_storage' => $tokenStorage,
            'security.csrf.token_manager' => $csrf,
            'request_stack' => $requestStack,
            'router' => new class {
                public function generate(string $route): string {
                    return '/app/perfil';
                }
            },
            default => null,
        });

        $controller = new LegacyProfileController($legacyService, $repo);
        $controller->setContainer($container);

        $response = $controller->revoke($uuid, $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app/perfil#herencia-digital', $response->getTargetUrl());
        $flashes = $session->getFlashBag()->get('info');
        $this->assertNotEmpty($flashes);
        $this->assertStringContainsString('1 certificat(s)', $flashes[0]);
    }
}
