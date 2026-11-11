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

    public function testReturnsDefaultDatabaseStatusWhenManagerNotProvided(): void
    {
        $status = $this->service->getDatabaseStatus();

        $this->assertFalse($status['exists']);
        $this->assertNull($status['updated_at']);
        $this->assertTrue($status['is_stale']);
        $this->assertFalse($status['has_license_key']);
    }

    public function testResolvesCfIpCountryHeaderOverLoopbackAndIgnoresUnknownMarkers(): void
    {
        $resFr = $this->service->resolveCountry('127.0.0.1', 'fr');
        $this->assertSame('FR', $resFr['code']);
        $this->assertSame('França', $resFr['name']);

        $resMx = $this->service->resolveCountry('127.0.0.1', 'MX');
        $this->assertSame('MX', $resMx['code']);
        $this->assertSame('Mèxic', $resMx['name']);

        // XX / T1 no han de sobreescriure la resolució normal
        $resUnknown = $this->service->resolveCountry('213.140.100.5', 'XX');
        $this->assertSame('AD', $resUnknown['code']);
    }

    public function testDoesNotClassifyPublicIpv6AsPrivateOrLoopback(): void
    {
        $method = new \ReflectionMethod(GeoLocationService::class, 'isPrivateOrLoopback');

        $this->assertTrue($method->invoke($this->service, '::1'));
        $this->assertTrue($method->invoke($this->service, 'fd00::1'));
        $this->assertFalse($method->invoke($this->service, '2001:4860:4860::8888'));
    }

    public function testAggregatesMixedCountryCodeAndIpRows(): void
    {
        $rows = [
            ['country_code' => 'DE', 'cnt' => 5],
            ['country_code' => 'ES', 'cnt' => 10],
            ['ip_address' => '51.15.20.10', 'cnt' => 5],
        ];

        $aggregated = $this->service->aggregateGeoTraffic($rows);

        $this->assertSame(20, $aggregated['totalRequests']);
        $this->assertSame('ES', $aggregated['topCountries'][0]['code']);
        $this->assertSame(50.0, $aggregated['topCountries'][0]['percent']);
        $this->assertCount(3, $aggregated['topCountries']);
    }
}
