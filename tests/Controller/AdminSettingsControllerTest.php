<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\AdminSettingsController;
use App\Repository\SystemSettingRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class AdminSettingsControllerTest extends TestCase
{
    private function createContainerWithServices(
        ?Environment $twig = null,
        ?UrlGeneratorInterface $router = null,
        ?Session $session = null
    ): ContainerInterface {
        $container = $this->createMock(ContainerInterface::class);
        $services = [];

        if ($twig !== null) {
            $services['twig'] = $twig;
        }
        if ($router !== null) {
            $services['router'] = $router;
        }
        if ($session !== null) {
            $requestStack = new RequestStack();
            $request = new Request();
            $request->setSession($session);
            $requestStack->push($request);
            $services['request_stack'] = $requestStack;
        }

        $container->method('has')->willReturnCallback(fn (string $id) => array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(fn (string $id) => $services[$id] ?? null);

        return $container;
    }

    public function testIndexGetRendersSettings(): void
    {
        $settingRepo = $this->createMock(SystemSettingRepository::class);
        $settingRepo->expects($this->once())
            ->method('getBool')
            ->with('ai_global_enabled', true)
            ->willReturn(true);

        $settingRepo->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(function (string $key, string $default) {
                return match ($key) {
                    'allowed_countries' => 'ES,AD,FR,IT,PT,DE',
                    'default_currency' => 'EUR',
                    'ai_sglang_endpoint' => 'http://127.0.0.1:30000/v1',
                    default => $default,
                };
            });

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('admin/settings/index.html.twig', $this->callback(function (array $params): bool {
                $this->assertTrue($params['aiGlobalEnabled']);
                $this->assertSame('ES,AD,FR,IT,PT,DE', $params['allowedCountries']);
                $this->assertSame('EUR', $params['defaultCurrency']);
                $this->assertSame('http://127.0.0.1:30000/v1', $params['sglangEndpoint']);
                return true;
            }))
            ->willReturn('<html>Settings</html>');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);

        $controller = new AdminSettingsController();
        $controller->setContainer($this->createContainerWithServices(twig: $twig));

        $request = new Request();
        $response = $controller->index($request, $settingRepo, $csrf);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('<html>Settings</html>', $response->getContent());
    }

    public function testIndexPostSavesSettingsWithValidCsrf(): void
    {
        $settingRepo = $this->createMock(SystemSettingRepository::class);
        $savedSettings = [];
        $settingRepo->expects($this->exactly(4))
            ->method('set')
            ->willReturnCallback(function (string $key, string $val, ?string $desc = null) use (&$savedSettings) {
                $savedSettings[$key] = $val;
            });

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())
            ->method('isTokenValid')
            ->with($this->callback(fn (CsrfToken $token) => $token->getValue() === 'valid_csrf'))
            ->willReturn(true);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_admin_settings')->willReturn('/admin/configuracio');

        $session = new Session(new MockArraySessionStorage());

        $controller = new AdminSettingsController();
        $controller->setContainer($this->createContainerWithServices(
            router: $router,
            session: $session
        ));

        $request = new Request(
            [],
            [
                '_csrf_token' => 'valid_csrf',
                'ai_global_enabled' => '1',
                'allowed_countries' => 'ES,AD,FR',
                'default_currency' => 'EUR',
                'ai_sglang_endpoint' => 'http://127.0.0.1:30000/v1',
            ]
        );
        $request->setMethod('POST');

        $response = $controller->index($request, $settingRepo, $csrf);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/admin/configuracio', $response->getTargetUrl());
        $this->assertSame('1', $savedSettings['ai_global_enabled'] ?? null);
        $this->assertSame('ES,AD,FR', $savedSettings['allowed_countries'] ?? null);
        $this->assertSame('EUR', $savedSettings['default_currency'] ?? null);
        $this->assertNotEmpty($session->getFlashBag()->get('success'));
    }

    public function testIndexPostRejectsInvalidCsrf(): void
    {
        $settingRepo = $this->createMock(SystemSettingRepository::class);
        $settingRepo->expects($this->never())->method('set');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(false);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_admin_settings')->willReturn('/admin/configuracio');

        $session = new Session(new MockArraySessionStorage());

        $controller = new AdminSettingsController();
        $controller->setContainer($this->createContainerWithServices(
            router: $router,
            session: $session
        ));

        $request = new Request(
            [],
            ['_csrf_token' => 'invalid_csrf']
        );
        $request->setMethod('POST');

        $response = $controller->index($request, $settingRepo, $csrf);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertNotEmpty($session->getFlashBag()->get('error'));
    }
}
