<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PageController;
use App\Entity\Plan;
use App\Repository\PlanRepository;
use App\Service\PlatformStatsService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class PageControllerTest extends TestCase
{
    public function testHomePassesPlansToTemplate(): void
    {
        $planFree = new Plan('free', 'Pla Free', 0);
        $planFree->setMaxActiveItems(20);

        $planPlus = new Plan('plus', 'Pla Plus', 2200);
        $planPlus->setMaxActiveItems(100);

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->expects($this->once())
            ->method('findBy')
            ->with(['isActive' => true])
            ->willReturn([$planFree, $planPlus]);

        $userRepo = $this->createMock(\App\Repository\UserRepository::class);
        $userRepo->method('countTotal')->willReturn(10);

        $itemRepo = $this->createMock(\App\Repository\ItemRepository::class);
        $itemRepo->method('countActiveTotal')->willReturn(150);
        $itemRepo->method('sumTotalDeclaredValue')->willReturn(500000);

        $certRepo = $this->createMock(\App\Repository\CertificateRepository::class);

        $statsService = new PlatformStatsService($userRepo, $itemRepo, $certRepo);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('page/home.html.twig', $this->callback(function (array $context) use ($planFree, $planPlus): bool {
                $this->assertArrayHasKey('stats', $context);
                $this->assertArrayHasKey('plans', $context);
                $this->assertSame($planFree, $context['plans']['free']);
                $this->assertSame($planPlus, $context['plans']['plus']);
                return true;
            }))
            ->willReturn('<html>Home</html>');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => in_array($id, ['security.token_storage', 'twig'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $twig) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'twig' => $twig,
                default => null,
            };
        });

        $controller = new PageController();
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->home($request, $statsService, $planRepo);

        $this->assertSame(200, $response->getStatusCode());
    }
}
