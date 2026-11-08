<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\GeoIp\GeoIpDatabaseManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeoIpDatabaseManagerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $baseTemp = dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'var' . \DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($baseTemp)) {
            @mkdir($baseTemp, 0777, true);
        }
        $this->tempDir = $baseTemp . \DIRECTORY_SEPARATOR . 'geoip_test_' . bin2hex(random_bytes(4));
        @mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
            }
            @rmdir($this->tempDir);
        }
    }

    public function testReportsNonExistentFileStatusCorrectly(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $manager = new GeoIpDatabaseManager($httpClient, new NullLogger(), $this->tempDir, null);

        $status = $manager->getDatabaseStatus();

        $this->assertFalse($status['exists']);
        $this->assertTrue($status['is_stale']);
        $this->assertNull($status['updated_at']);
        $this->assertFalse($status['has_license_key']);
    }

    public function testUpdateFailsGracefullyWhenNoLicenseKeyProvided(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $manager = new GeoIpDatabaseManager($httpClient, new NullLogger(), $this->tempDir, null);

        $res = $manager->updateDatabase();

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('MAXMIND_LICENSE_KEY', $res['message']);
    }

    public function testManualImportCopiesFileToTarget(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $manager = new GeoIpDatabaseManager($httpClient, new NullLogger(), $this->tempDir, 'test_key');

        $dummySource = $this->tempDir . \DIRECTORY_SEPARATOR . 'dummy.mmdb';
        file_put_contents($dummySource, 'DUMMY_MMDB_DATA');

        $res = $manager->importDatabaseFile($dummySource);

        $this->assertTrue($res['success']);
        $this->assertFileExists($manager->getDatabasePath());
        $this->assertSame('DUMMY_MMDB_DATA', file_get_contents($manager->getDatabasePath()));

        $status = $manager->getDatabaseStatus();
        $this->assertTrue($status['exists']);
        $this->assertFalse($status['is_stale']);
        $this->assertSame(0, $status['age_days']);
        $this->assertTrue($status['has_license_key']);
    }
}
