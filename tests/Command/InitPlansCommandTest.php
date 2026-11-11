<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\InitPlansCommand;
use App\Entity\Plan;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitPlansCommandTest extends TestCase
{
    public function testPreservesExistingLivePlansWithoutForceReset(): void
    {
        $existingFree = (new Plan('free', 'Pla Free', 0))
            ->setMaxActiveItems(10)
            ->setMaxYearlyRegistrations(10)
            ->setAiAssistanceQuota(10);
        $existingPlus = (new Plan('plus', 'Pla Plus', 2499))
            ->setAllowOfficialCertificates(true);
        $existingUnlimited = (new Plan('unlimited', 'Pla Unlimited / Business', 5999))
            ->setAiAssistanceQuota(1000)
            ->setMaxPhotosPerItem(10)
            ->setMaxEvidencesPerItem(10);

        $repo = $this->createMock(PlanRepository::class);
        $repo->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Plan => match ($criteria['code'] ?? null) {
            'free' => $existingFree,
            'plus' => $existingPlus,
            'unlimited' => $existingUnlimited,
            default => null,
        });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->once())->method('flush');

        $command = new InitPlansCommand($em, $repo);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertSame('Pla Free', $existingFree->getName());
        $this->assertSame(10, $existingFree->getMaxActiveItems());
        $this->assertSame(2499, $existingPlus->getPriceCents());
        $this->assertSame(5999, $existingUnlimited->getPriceCents());
        $this->assertSame(1000, $existingUnlimited->getAiAssistanceQuota());
        $this->assertStringContainsString('Preserving existing plan', $tester->getDisplay());
    }
}

