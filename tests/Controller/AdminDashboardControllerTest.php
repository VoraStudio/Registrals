<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\AdminDashboardController;
use App\Entity\Plan;
use App\Repository\ItemDocumentRepository;
use App\Repository\ItemRepository;
use App\Repository\PlanRepository;
use App\Repository\SystemSettingRepository;
use App\Repository\UserRepository;
use App\Service\GeoIp\GeoLocationService;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Twig\Environment;

class AdminDashboardControllerTest extends TestCase
{
    public function testDashboardComputesArrDynamicallyFromPlanEntities(): void
    {
        $planFree = new Plan('free', 'Pla Free', 0);
        $planPlus = new Plan('plus', 'Pla Plus', 2499);
        $planPlus->setBillingPeriod('yearly');
        $planUnlimited = new Plan('unlimited', 'Pla Unlimited / Business', 599);
        $planUnlimited->setBillingPeriod('monthly');

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('countTotal')->willReturn(15);
        $userRepo->method('countActive')->willReturn(15);
        $userRepo->method('countBlocked')->willReturn(0);
        $userRepo->method('countUsersByPlan')->willReturn(['free' => 10, 'plus' => 3, 'unlimited' => 2]);
        $userRepo->method('findRecent')->willReturn([]);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('countTotal')->willReturn(21);
        $itemRepo->method('countActiveTotal')->willReturn(20);
        $itemRepo->method('findRecent')->willReturn([]);

        $docRepo = $this->createMock(ItemDocumentRepository::class);
        $docRepo->method('countTotal')->willReturn(12);
        $docRepo->method('sumTotalBytes')->willReturn(1024);

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->expects($this->once())
            ->method('findAll')
            ->willReturn([$planFree, $planPlus, $planUnlimited]);

        $settingRepo = $this->createMock(SystemSettingRepository::class);
        $settingRepo->method('getBool')->willReturn(true);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getConnection')->willThrowException(new \RuntimeException('No DB in unit test'));

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \RuntimeException('No local SGLang'));

        $geoService = $this->createMock(GeoLocationService::class);
        $geoService->method('aggregateGeoTraffic')->willReturn([
            'chartData' => [],
            'topCountries' => [],
            'totalRequests' => 0,
        ]);
        $geoService->method('getDatabaseStatus')->willReturn([
            'exists' => false,
            'age_days' => null,
            'has_license_key' => false,
        ]);

        // ARR esperat: (3 * 24,99 * 1) + (2 * 5,99 * 12) = 74,97 + 143,76 = 218,73 €
        $expectedArr = (3 * 24.99) + (2 * 5.99 * 12);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('admin/dashboard.html.twig', $this->callback(function (array $params) use ($expectedArr, $planFree, $planPlus, $planUnlimited): bool {
                $this->assertEqualsWithDelta($expectedArr, $params['estimatedArr'], 0.0001);
                $this->assertSame($planFree, $params['plansByCode']['free']);
                $this->assertSame($planPlus, $params['plansByCode']['plus']);
                $this->assertSame($planUnlimited, $params['plansByCode']['unlimited']);
                return true;
            }))
            ->willReturn('<html>Dashboard</html>');

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => $id === 'twig');
        $container->method('get')->willReturnCallback(fn (string $id) => $id === 'twig' ? $twig : null);

        $controller = new AdminDashboardController();
        $controller->setContainer($container);

        $response = $controller->index(
            $userRepo,
            $itemRepo,
            $docRepo,
            $planRepo,
            $settingRepo,
            $doctrine,
            $httpClient,
            $geoService
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testApiGeoReturnsAggregatedJson(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getConnection')->willThrowException(new \RuntimeException('No DB'));

        $geoService = $this->createMock(GeoLocationService::class);
        $geoService->expects($this->once())
            ->method('aggregateGeoTraffic')
            ->with([])
            ->willReturn([
                'chartData' => [['Country', 'Peticions'], ['ES', 1]],
                'topCountries' => [['code' => 'ES', 'name' => 'Espanya', 'count' => 1, 'percent' => 100.0]],
                'totalRequests' => 1,
            ]);

        $controller = new AdminDashboardController();
        $response = $controller->apiGeo($doctrine, $geoService);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(1, $data['totalRequests']);
        $this->assertSame('ES', $data['topCountries'][0]['code']);
    }
}

