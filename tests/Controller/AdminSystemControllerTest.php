<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\AdminSystemController;
use App\Repository\SystemSettingRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

class AdminSystemControllerTest extends TestCase
{
    private function createContainerWithTwig(callable $assertParameters): ContainerInterface
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $template, array $parameters) use ($assertParameters) {
                $this->assertSame('admin/system/services.html.twig', $template);
                $assertParameters($parameters);
                return '<html>rendered</html>';
            });

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => in_array($id, ['twig', 'parameter_bag'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($twig) {
            if ($id === 'twig') {
                return $twig;
            }
            if ($id === 'parameter_bag') {
                $bag = new class {
                    public function get(string $name): string {
                        return sys_get_temp_dir();
                    }
                };
                return $bag;
            }
            return null;
        });

        return $container;
    }

    public function testServicesRendersMessengerMetricsAndCleanQueue(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $conn = $this->createMock(Connection::class);
        $connAudit = $this->createMock(Connection::class);

        $doctrine->method('getConnection')->willReturnCallback(function (string $name) use ($conn, $connAudit) {
            return $name === 'audit' ? $connAudit : $conn;
        });

        $resultDb = $this->createMock(Result::class);
        $resultDb->method('fetchOne')->willReturn(1);

        $resultAudit = $this->createMock(Result::class);
        $resultAudit->method('fetchOne')->willReturn(1);

        $resultMessenger = $this->createMock(Result::class);
        $resultMessenger->method('fetchAllAssociative')->willReturn([
            ['queue_name' => 'async_ai', 'cnt' => 3],
            ['queue_name' => 'async_chain', 'cnt' => 1],
            ['queue_name' => 'failed', 'cnt' => 0],
        ]);

        $conn->method('executeQuery')->willReturnCallback(function (string $sql) use ($resultDb, $resultMessenger) {
            if (str_contains($sql, 'messenger_messages')) {
                return $resultMessenger;
            }
            return $resultDb;
        });

        $connAudit->method('executeQuery')->willReturn($resultAudit);

        $settingRepo = $this->createMock(SystemSettingRepository::class);
        $settingRepo->method('getBool')->willReturn(true);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $controller = new AdminSystemController();
        $controller->setContainer($this->createContainerWithTwig(function (array $params): void {
            $this->assertArrayHasKey('messengerStats', $params);
            $this->assertSame(3, $params['messengerStats']['async_ai']);
            $this->assertSame(1, $params['messengerStats']['async_chain']);
            $this->assertSame(0, $params['messengerStats']['failed']);
            $this->assertSame(4, $params['messengerStats']['total_pending']);
            $this->assertArrayHasKey('failedMessages', $params);
            $this->assertEmpty($params['failedMessages']);
        }));

        $response = $controller->services($doctrine, $settingRepo, $httpClient);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRetryMessengerRejectsInvalidCsrf(): void
    {
        $controller = new AdminSystemController();

        $session = new Session(new MockArraySessionStorage());
        $csrfManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn(false);

        $request = new Request([], ['_token' => 'invalid_csrf']);
        $request->setSession($session);

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $requestStack->push($request);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => in_array($id, ['security.csrf.token_manager', 'router', 'request_stack'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($csrfManager, $requestStack) {
            if ($id === 'security.csrf.token_manager') {
                return $csrfManager;
            }
            if ($id === 'router') {
                $router = $this->createMock(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
                $router->method('generate')->willReturn('/admin/serveis');
                return $router;
            }
            if ($id === 'request_stack') {
                return $requestStack;
            }
            return null;
        });

        $controller->setContainer($container);

        $kernel = $this->createMock(KernelInterface::class);

        $response = $controller->retryMessengerMessage(42, $request, $kernel);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/admin/serveis', $response->headers->get('Location'));
        $this->assertNotEmpty($session->getFlashBag()->get('error'));
    }
}
