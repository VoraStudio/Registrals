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
            ->method('countTotal')
            ->willReturn(9);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->expects($this->once())
            ->method('countActiveTotal')
            ->willReturn(14);
        $itemRepo->expects($this->once())
            ->method('sumTotalDeclaredValue')
            ->willReturn(333400);

        $certRepo = $this->createMock(CertificateRepository::class);

        $service = new PlatformStatsService($userRepo, $itemRepo, $certRepo);
        $stats = $service->getHomeStats();

        $this->assertSame(9, $stats['registeredUsers']);
        $this->assertSame(14, $stats['certifiedItems']);
        $this->assertSame(333400, $stats['totalDeclaredValueCents']);
        $this->assertSame(3334.0, $stats['totalDeclaredValueEuros']);
        $this->assertSame('3.334€', $stats['totalDeclaredValueFormatted']);
    }

    public function testGetHomeStatsHandlesZeroValues(): void
    {
        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('countTotal')->willReturn(0);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('countActiveTotal')->willReturn(0);
        $itemRepo->method('sumTotalDeclaredValue')->willReturn(0);

        $certRepo = $this->createMock(CertificateRepository::class);

        $service = new PlatformStatsService($userRepo, $itemRepo, $certRepo);
        $stats = $service->getHomeStats();

        $this->assertSame(0, $stats['registeredUsers']);
        $this->assertSame(0, $stats['certifiedItems']);
        $this->assertSame(0, $stats['totalDeclaredValueCents']);
        $this->assertSame(0.0, $stats['totalDeclaredValueEuros']);
        $this->assertSame('0€', $stats['totalDeclaredValueFormatted']);
    }
}
