<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\GeoIp\GeoLocationService;
use PHPUnit\Framework\TestCase;

class GeoLocationServiceTest extends TestCase
{
    private GeoLocationService $service;

    protected function setUp(): void
    {
        $this->service = new GeoLocationService();
    }

    public function testResolvesLoopbackAndPrivateIpToSpain(): void
    {
        $resLocal = $this->service->resolveCountry('127.0.0.1');
        $this->assertSame('ES', $resLocal['code']);
        $this->assertSame('Espanya', $resLocal['name']);

        $resPrivate = $this->service->resolveCountry('192.168.1.100');
        $this->assertSame('ES', $resPrivate['code']);

        $resEmpty = $this->service->resolveCountry(null);
        $this->assertSame('ES', $resEmpty['code']);
    }

    public function testResolvesKnownPublicIps(): void
    {
        $resSpain = $this->service->resolveCountry('83.43.195.95');
        $this->assertSame('ES', $resSpain['code']);

        $resFrance = $this->service->resolveCountry('51.15.20.10');
        $this->assertSame('FR', $resFrance['code']);
        $this->assertSame('França', $resFrance['name']);

        $resAndorra = $this->service->resolveCountry('213.140.100.5');
        $this->assertSame('AD', $resAndorra['code']);
        $this->assertSame('Andorra', $resAndorra['name']);
    }

    public function testAggregatesGeoTrafficForGoogleGeoChart(): void
    {
        $rawRows = [
            ['ip_address' => '83.43.195.95', 'cnt' => 10],
            ['ip_address' => '51.15.20.10', 'cnt' => 4],
            ['ip_address' => '127.0.0.1', 'cnt' => 6],
        ];

        $aggregated = $this->service->aggregateGeoTraffic($rawRows);

        $this->assertArrayHasKey('chartData', $aggregated);
        $this->assertArrayHasKey('topCountries', $aggregated);
        $this->assertSame(20, $aggregated['totalRequests']);

        // First row of chartData must be the header for Google GeoChart
        $this->assertSame(['Country', 'Peticions'], $aggregated['chartData'][0]);

        // Top country should be ES (10 + 6 = 16)
        $this->assertSame('ES', $aggregated['topCountries'][0]['code']);
        $this->assertSame(16, $aggregated['topCountries'][0]['count']);
        $this->assertSame(80.0, $aggregated['topCountries'][0]['percent']);

        // Second should be FR (4)
        $this->assertSame('FR', $aggregated['topCountries'][1]['code']);
        $this->assertSame(4, $aggregated['topCountries'][1]['count']);
        $this->assertSame(20.0, $aggregated['topCountries'][1]['percent']);
    }
}
