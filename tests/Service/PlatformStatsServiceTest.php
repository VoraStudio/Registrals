<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\UserRepository;
use App\Service\PlatformStatsService;
use PHPUnit\Framework\TestCase;

class PlatformStatsServiceTest extends TestCase
{
    public function testGetHomeStatsReturnsComputedMetrics(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->expects($this->once())
            ->method('countNonAdmin')
            ->willReturn(9);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->expects($this->once())
            ->method('countCustodiedTotal')
            ->willReturn(21);

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->expects($this->once())
            ->method('countValid')
            ->willReturn(5);

        $service = new PlatformStatsService($userRepo, $itemRepo, $certRepo, 0);
        $stats = $service->getHomeStats();

        $this->assertSame(9, $stats['registeredUsers']);
        $this->assertSame(21, $stats['custodiedItems']);
        $this->assertSame(5, $stats['issuedCertificates']);
        $this->assertTrue($stats['showStats']);
    }

    public function testGetHomeStatsHidesStatsBelowThresholdAndShowsAtThreshold(): void
    {
        $userRepoBelow = $this->createMock(UserRepository::class);
        $userRepoBelow->method('countNonAdmin')->willReturn(999);

        $itemRepoBelow = $this->createMock(ItemRepository::class);
        $itemRepoBelow->method('countCustodiedTotal')->willReturn(1500);

        $certRepoBelow = $this->createMock(CertificateRepository::class);
        $certRepoBelow->method('countValid')->willReturn(400);

        $this->assertSame(1000, PlatformStatsService::DEFAULT_MIN_THRESHOLD);

        $serviceBelow = new PlatformStatsService($userRepoBelow, $itemRepoBelow, $certRepoBelow);
        $statsBelow = $serviceBelow->getHomeStats();

        $this->assertSame(999, $statsBelow['registeredUsers']);
        $this->assertFalse($statsBelow['showStats']);

        $userRepoAt = $this->createMock(UserRepository::class);
        $userRepoAt->method('countNonAdmin')->willReturn(1000);

        $serviceAt = new PlatformStatsService($userRepoAt, $itemRepoBelow, $certRepoBelow);
        $statsAt = $serviceAt->getHomeStats();

        $this->assertTrue($statsAt['showStats']);
    }

    public function testGetHomeStatsHandlesZeroValues(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('countNonAdmin')->willReturn(0);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('countCustodiedTotal')->willReturn(0);

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('countValid')->willReturn(0);

        $service = new PlatformStatsService($userRepo, $itemRepo, $certRepo, 0);
        $stats = $service->getHomeStats();

        $this->assertSame(0, $stats['registeredUsers']);
        $this->assertSame(0, $stats['custodiedItems']);
        $this->assertSame(0, $stats['issuedCertificates']);
        $this->assertTrue($stats['showStats']);
    }
}
