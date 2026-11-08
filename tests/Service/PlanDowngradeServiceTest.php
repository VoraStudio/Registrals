<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Audit\Service\UserActivityLogger;
use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\ItemStatus;
use App\Repository\ItemRepository;
use App\Repository\PlanRepository;
use App\Service\Plan\PlanDowngradeService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PlanDowngradeServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private PlanRepository $planRepository;
    private ItemRepository $itemRepository;
    private PlanDowngradeService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->planRepository = $this->createMock(PlanRepository::class);
        $this->itemRepository = $this->createMock(ItemRepository::class);

        $this->service = new PlanDowngradeService(
            $this->entityManager,
            $this->planRepository,
            $this->itemRepository,
            null,
            new NullLogger()
        );
    }

    public function testDowngradeUserToFreeWithoutExceedingLimit(): void
    {
        $user = new User();
        $freePlan = new Plan('free', 'Free', 0);
        $freePlan->setMaxActiveItems(20);

        $this->planRepository->method('findByCode')->with('free')->willReturn($freePlan);
        $this->itemRepository->method('countActiveByOwner')->with($user)->willReturn(10);

        $this->entityManager->expects($this->once())->method('persist')->with($user);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->downgradeUserToFree($user);

        $this->assertSame($freePlan, $user->getPlan());
        $this->assertSame('canceled', $user->getSubscriptionStatus());
        $this->assertNull($user->getDowngradeGracePeriodExpiresAt());
    }

    public function testDowngradeUserToFreeWithGracePeriodWhenExceedingLimit(): void
    {
        $user = new User();
        $freePlan = new Plan('free', 'Free', 0);
        $freePlan->setMaxActiveItems(20);

        $this->planRepository->method('findByCode')->with('free')->willReturn($freePlan);
        $this->itemRepository->method('countActiveByOwner')->with($user)->willReturn(25);

        $this->entityManager->expects($this->once())->method('persist')->with($user);
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->downgradeUserToFree($user);

        $this->assertSame($freePlan, $user->getPlan());
        $this->assertSame('canceled', $user->getSubscriptionStatus());
        $this->assertNotNull($user->getDowngradeGracePeriodExpiresAt());
        $this->assertTrue($user->isInDowngradeGracePeriod());
    }

    public function testEnforceDowngradeLimitsFreezesExcessItemsWhenExpired(): void
    {
        $user = new User();
        $freePlan = new Plan('free', 'Free', 0);
        $freePlan->setMaxActiveItems(2);
        $user->setPlan($freePlan);
        // Període de gràcia expirat
        $user->setDowngradeGracePeriodExpiresAt(new \DateTimeImmutable('-1 hour'));

        $category = new Category('Rellotges', 'rellotges');
        $item1 = new Item($user, 'REG-001', 'Item 1', $category);
        $item2 = new Item($user, 'REG-002', 'Item 2', $category);
        $item3 = new Item($user, 'REG-003', 'Item 3', $category);

        $this->itemRepository->method('findAllForDowngradeSelection')->with($user)->willReturn([$item1, $item2, $item3]);
        $this->entityManager->expects($this->once())->method('flush');

        $frozenCount = $this->service->enforceDowngradeLimits($user);

        $this->assertSame(1, $frozenCount);
        $this->assertSame(ItemStatus::ACTIVE, $item1->getStatus());
        $this->assertSame(ItemStatus::ACTIVE, $item2->getStatus());
        $this->assertSame(ItemStatus::FROZEN, $item3->getStatus());
        $this->assertNull($user->getDowngradeGracePeriodExpiresAt());
    }

    public function testSelectActiveItems(): void
    {
        $user = new User();
        $freePlan = new Plan('free', 'Free', 0);
        $freePlan->setMaxActiveItems(2);
        $user->setPlan($freePlan);

        $category = new Category('Rellotges', 'rellotges');
        $item1 = new Item($user, 'REG-001', 'Item 1', $category);
        $item2 = new Item($user, 'REG-002', 'Item 2', $category);
        $item3 = new Item($user, 'REG-003', 'Item 3', $category);

        $reflection = new \ReflectionProperty(Item::class, 'id');
        $reflection->setValue($item1, 101);
        $reflection->setValue($item2, 102);
        $reflection->setValue($item3, 103);

        $this->itemRepository->method('findAllForDowngradeSelection')->with($user)->willReturn([$item1, $item2, $item3]);
        $this->entityManager->expects($this->once())->method('flush');

        // L'usuari tria l'ítem 101 i 103 com a actius
        $success = $this->service->selectActiveItems($user, [101, 103]);

        $this->assertTrue($success);
        $this->assertSame(ItemStatus::ACTIVE, $item1->getStatus());
        $this->assertSame(ItemStatus::FROZEN, $item2->getStatus());
        $this->assertSame(ItemStatus::ACTIVE, $item3->getStatus());
        $this->assertNull($user->getDowngradeGracePeriodExpiresAt());
    }

    public function testUnfreezeUserItems(): void
    {
        $user = new User();
        $plusPlan = new Plan('plus', 'Plus', 2200);
        $plusPlan->setMaxActiveItems(100);
        $user->setPlan($plusPlan);

        $category = new Category('Rellotges', 'rellotges');
        $item1 = new Item($user, 'REG-001', 'Item 1', $category);
        $item1->setStatus(ItemStatus::FROZEN);

        $item2 = new Item($user, 'REG-002', 'Item 2', $category);
        $item2->setStatus(ItemStatus::FROZEN);

        $this->itemRepository->method('findFrozenItemsByOwner')->with($user, 1, 9999)->willReturn([$item1, $item2]);
        $this->entityManager->expects($this->once())->method('flush');

        $unfrozenCount = $this->service->unfreezeUserItems($user);

        $this->assertSame(2, $unfrozenCount);
        $this->assertSame(ItemStatus::ACTIVE, $item1->getStatus());
        $this->assertSame(ItemStatus::ACTIVE, $item2->getStatus());
    }

    public function testCheckAndEnforceQuotaWithinLimitClearsGracePeriod(): void
    {
        $user = new User();
        $freePlan = new Plan('free', 'Free', 0);
        $freePlan->setMaxActiveItems(10);
        $user->setPlan($freePlan);
        $user->setDowngradeGracePeriodExpiresAt(new \DateTimeImmutable('+3 days'));

        $this->itemRepository->method('countActiveByOwner')->with($user)->willReturn(5);
        $this->entityManager->expects($this->once())->method('flush');

        $frozen = $this->service->checkAndEnforceQuota($user);

        $this->assertSame(0, $frozen);
        $this->assertNull($user->getDowngradeGracePeriodExpiresAt());
    }

    public function testCheckAndEnforceQuotaOverLimitStartsGracePeriodWhenNull(): void
    {
        $user = new User();
        $freePlan = new Plan('free', 'Free', 0);
        $freePlan->setMaxActiveItems(5);
        $user->setPlan($freePlan);
        $user->setDowngradeGracePeriodExpiresAt(null);

        $this->itemRepository->method('countActiveByOwner')->with($user)->willReturn(7);
        $this->entityManager->expects($this->once())->method('flush');

        $frozen = $this->service->checkAndEnforceQuota($user);

        $this->assertSame(0, $frozen);
        $this->assertNotNull($user->getDowngradeGracePeriodExpiresAt());
        $this->assertTrue($user->isInDowngradeGracePeriod());
    }

    public function testCheckAndEnforceQuotaOverLimitFreezesWhenGracePeriodExpired(): void
    {
        $user = new User();
        $freePlan = new Plan('free', 'Free', 0);
        $freePlan->setMaxActiveItems(2);
        $user->setPlan($freePlan);
        $user->setDowngradeGracePeriodExpiresAt(new \DateTimeImmutable('-1 hour'));

        $category = new Category('Art', 'art');
        $item1 = new Item($user, 'REG-001', 'Item 1', $category);
        $item1->setStatus(ItemStatus::ACTIVE);
        $item2 = new Item($user, 'REG-002', 'Item 2', $category);
        $item2->setStatus(ItemStatus::ACTIVE);
        $item3 = new Item($user, 'REG-003', 'Item 3', $category);
        $item3->setStatus(ItemStatus::ACTIVE);

        $this->itemRepository->method('countActiveByOwner')->with($user)->willReturn(3);
        $this->itemRepository->method('findAllForDowngradeSelection')->with($user)->willReturn([$item1, $item2, $item3]);

        $this->entityManager->expects($this->once())->method('flush');

        $frozen = $this->service->checkAndEnforceQuota($user);

        $this->assertSame(1, $frozen);
        $this->assertSame(ItemStatus::ACTIVE, $item1->getStatus());
        $this->assertSame(ItemStatus::ACTIVE, $item2->getStatus());
        $this->assertSame(ItemStatus::FROZEN, $item3->getStatus());
        $this->assertNull($user->getDowngradeGracePeriodExpiresAt());
    }
}
