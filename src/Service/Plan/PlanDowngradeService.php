<?php

declare(strict_types=1);

namespace App\Service\Plan;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\ItemStatus;
use App\Repository\ItemRepository;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Gestiona el cicle de vida dels downgrades de pla (Plus -> Free),
 * el període de gràcia de 7 dies per a l'adequació del patrimoni,
 * la congelació automàtica de béns excedents a custòdia en fred (FROZEN),
 * i la descongelació íntegra en reactivar el Pla Plus.
 */
class PlanDowngradeService
{
    public const GRACE_PERIOD_DAYS = 7;
    public const DEFAULT_FREE_LIMIT = 20;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanRepository $planRepository,
        private readonly ItemRepository $itemRepository,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Rebaixa un compte d'usuari al Pla Free per cancel·lació de subscripció o impagament.
     */
    public function downgradeUserToFree(User $user): void
    {
        $freePlan = $this->planRepository->findByCode('free');
        if ($freePlan !== null) {
            $user->setPlan($freePlan);
        }

        $user->setSubscriptionStatus('canceled');

        $activeCount = $this->itemRepository->countActiveByOwner($user);
        $maxFreeItems = $freePlan?->getMaxActiveItems() ?? self::DEFAULT_FREE_LIMIT;

        if ($activeCount > $maxFreeItems) {
            // Activa el període de gràcia de 7 dies per a la selecció dels 20 béns
            $graceExpiry = (new \DateTimeImmutable())->modify(sprintf('+%d days', self::GRACE_PERIOD_DAYS));
            $user->setDowngradeGracePeriodExpiresAt($graceExpiry);

            $this->logger?->info(sprintf('Usuari %d rebaixat a Pla Free amb període de gràcia de %d dies (%d béns actius)', $user->getId(), self::GRACE_PERIOD_DAYS, $activeCount));

            $this->activityLogger?->log(
                user: $user,
                action: UserActivityAction::STATUS_CHANGED,
                description: sprintf('Inici de període de gràcia de %d dies per adequació al límit de %d béns del Pla Free', self::GRACE_PERIOD_DAYS, $maxFreeItems),
                details: [
                    'active_items_count' => $activeCount,
                    'free_limit' => $maxFreeItems,
                    'grace_period_days' => self::GRACE_PERIOD_DAYS,
                    'grace_expires_at' => $graceExpiry->format(\DateTimeInterface::ATOM),
                ]
            );
        } else {
            $user->setDowngradeGracePeriodExpiresAt(null);
            $this->logger?->info(sprintf('Usuari %d rebaixat a Pla Free de forma neta (%d béns <= %d límit)', $user->getId(), $activeCount, $maxFreeItems));
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Comprova la invariant de quota del pla de l'usuari (Self-Healing Invariance Check).
     * Si els béns actius superen el límit permès:
     * - Si no té període de gràcia, n'activa un de 7 dies de forma respectuosa.
     * - Si el període de gràcia ha vençut, aplica la congelació automàtica dels excedents a FROZEN.
     * - Si està dins de quota i tenia període de gràcia pendent, el neteja.
     *
     * @return int Nombre de béns congelats (0 si no s'ha congelat cap)
     */
    public function checkAndEnforceQuota(User $user): int
    {
        $plan = $user->getPlan();
        $maxAllowed = $plan?->getMaxActiveItems() ?? self::DEFAULT_FREE_LIMIT;

        // Si el pla té quota il·limitada (<= 0), no hi ha sobrequota
        if ($maxAllowed <= 0) {
            if ($user->getDowngradeGracePeriodExpiresAt() !== null) {
                $user->setDowngradeGracePeriodExpiresAt(null);
                $this->entityManager->flush();
            }
            return 0;
        }

        $activeCount = $this->itemRepository->countActiveByOwner($user);

        if ($activeCount > $maxAllowed) {
            $graceExpiry = $user->getDowngradeGracePeriodExpiresAt();

            // Cas 1: Encara no té període de gràcia assignat -> Activem finestra de 7 dies
            if ($graceExpiry === null) {
                $newExpiry = (new \DateTimeImmutable())->modify(sprintf('+%d days', self::GRACE_PERIOD_DAYS));
                $user->setDowngradeGracePeriodExpiresAt($newExpiry);
                $this->entityManager->flush();

                $this->activityLogger?->log(
                    user: $user,
                    action: UserActivityAction::STATUS_CHANGED,
                    description: sprintf('Inici de període d\'adequació de %d dies per sobrequota (%d béns > límit de %d)', self::GRACE_PERIOD_DAYS, $activeCount, $maxAllowed),
                    details: [
                        'active_items_count' => $activeCount,
                        'plan_limit' => $maxAllowed,
                        'grace_period_days' => self::GRACE_PERIOD_DAYS,
                        'grace_expires_at' => $newExpiry->format(\DateTimeInterface::ATOM),
                    ]
                );

                $this->logger?->info(sprintf('Usuari %d en sobrequota (%d > %d). Període de gràcia de %d dies activat.', $user->getId(), $activeCount, $maxAllowed, self::GRACE_PERIOD_DAYS));

                return 0;
            }

            // Cas 2: El període de gràcia ha vençut -> Congelem els excedents
            if ($graceExpiry <= new \DateTimeImmutable()) {
                return $this->enforceDowngradeLimits($user, force: true);
            }

            // Cas 3: El període de gràcia és vigent -> Manté la gràcia sense congelar encara
            return 0;
        }

        // Si està dins del límit però tenia una data de gràcia pendent, la netegem
        if ($user->getDowngradeGracePeriodExpiresAt() !== null) {
            $user->setDowngradeGracePeriodExpiresAt(null);
            $this->entityManager->flush();
        }

        return 0;
    }

    /**
     * Aplica la congelació automàtica si el període de gràcia ha expirat o es força l'execució.
     * Manté els 20 béns més recents com a actius i congela els excedents (FROZEN).
     *
     * @return int Nombre de béns congelats
     */
    public function enforceDowngradeLimits(User $user, bool $force = false): int
    {
        $graceExpiry = $user->getDowngradeGracePeriodExpiresAt();
        $isExpired = $graceExpiry !== null && $graceExpiry <= new \DateTimeImmutable();

        if (!$force && !$isExpired) {
            return 0;
        }

        $maxFreeItems = $user->getPlan()?->getMaxActiveItems() ?? self::DEFAULT_FREE_LIMIT;
        if ($maxFreeItems <= 0) {
            $maxFreeItems = self::DEFAULT_FREE_LIMIT;
        }

        /** @var list<Item> $allItems */
        $allItems = $this->itemRepository->findAllForDowngradeSelection($user);

        if (count($allItems) <= $maxFreeItems) {
            $user->setDowngradeGracePeriodExpiresAt(null);
            $this->entityManager->flush();
            return 0;
        }

        $frozenCount = 0;
        foreach ($allItems as $index => $item) {
            if ($index < $maxFreeItems) {
                // Els primers N més recents es mantenen o retornen a actius
                if ($item->getStatus() !== ItemStatus::ACTIVE) {
                    $item->setStatus(ItemStatus::ACTIVE);
                }
            } else {
                // Els excedents passen a custòdia en fred
                if ($item->getStatus() !== ItemStatus::FROZEN) {
                    $item->setStatus(ItemStatus::FROZEN);
                    $frozenCount++;
                }
            }
        }

        $user->setDowngradeGracePeriodExpiresAt(null);
        $this->entityManager->flush();

        if ($frozenCount > 0) {
            $this->logger?->info(sprintf('Congelats %d béns excedents per a l\'usuari %d en finalitzar el període de gràcia', $frozenCount, $user->getId()));

            $this->activityLogger?->log(
                user: $user,
                action: UserActivityAction::STATUS_CHANGED,
                description: sprintf('Congelació automàtica de %d béns en custòdia en fred per superació de quota del Pla Free', $frozenCount),
                details: [
                    'items_frozen_count' => $frozenCount,
                    'kept_active_count' => $maxFreeItems,
                ]
            );
        }

        return $frozenCount;
    }

    /**
     * Permet a l'usuari triar manualment quins N béns vol mantenir com a actius (màxim el límit Free).
     * Els béns seleccionats queden com a ACTIVE i la resta passen a FROZEN.
     *
     * @param list<int> $selectedItemIds
     */
    public function selectActiveItems(User $user, array $selectedItemIds): bool
    {
        $maxFreeItems = $user->getPlan()?->getMaxActiveItems() ?? self::DEFAULT_FREE_LIMIT;
        if ($maxFreeItems <= 0) {
            $maxFreeItems = self::DEFAULT_FREE_LIMIT;
        }

        if (count($selectedItemIds) > $maxFreeItems) {
            return false;
        }

        /** @var list<Item> $allItems */
        $allItems = $this->itemRepository->findAllForDowngradeSelection($user);

        $selectedLookup = array_flip($selectedItemIds);
        $frozenCount = 0;

        foreach ($allItems as $item) {
            $itemId = $item->getId();
            if ($itemId !== null && isset($selectedLookup[$itemId])) {
                $item->setStatus(ItemStatus::ACTIVE);
            } else {
                $item->setStatus(ItemStatus::FROZEN);
                $frozenCount++;
            }
        }

        $user->setDowngradeGracePeriodExpiresAt(null);
        $this->entityManager->flush();

        $this->activityLogger?->log(
            user: $user,
            action: UserActivityAction::STATUS_CHANGED,
            description: sprintf('Selecció manual d\'adequació de patrimoni: %d actius i %d en custòdia en fred', count($selectedItemIds), $frozenCount),
            details: [
                'active_selected_ids' => $selectedItemIds,
                'items_frozen_count' => $frozenCount,
            ]
        );

        return true;
    }

    /**
     * Descongela íntegrament tots els béns d'un usuari quan reactiva el Pla Plus.
     *
     * @return int Nombre de béns descongelats
     */
    public function unfreezeUserItems(User $user): int
    {
        $frozenItems = $this->itemRepository->findFrozenItemsByOwner($user, 1, 9999);
        $unfrozenCount = count($frozenItems);

        foreach ($frozenItems as $item) {
            $item->setStatus(ItemStatus::ACTIVE);
        }

        $user->setDowngradeGracePeriodExpiresAt(null);
        $this->entityManager->flush();

        if ($unfrozenCount > 0) {
            $this->logger?->info(sprintf('Descongelats %d béns per a l\'usuari %d en reactivar el Pla Plus', $unfrozenCount, $user->getId()));

            $this->activityLogger?->log(
                user: $user,
                action: UserActivityAction::STATUS_CHANGED,
                description: sprintf('Reactivació del Pla Plus: %d béns en custòdia en fred han estat completament desbloquejats', $unfrozenCount),
                details: [
                    'unfrozen_items_count' => $unfrozenCount,
                ]
            );
        }

        return $unfrozenCount;
    }
}
