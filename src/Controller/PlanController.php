<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PlanController extends AbstractController
{
    #[Route('/app/perfil/pla', name: 'app_plan', methods: ['GET'])]
    #[Route('/app/plan', name: 'app_plan_legacy', methods: ['GET'])]
    public function index(
        PlanRepository $planRepository,
        \App\Repository\ItemRepository $itemRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $currentPlan = $user->getPlan() ?? $planRepository->findByCode('free');
        if ($currentPlan === null) {
            throw $this->createNotFoundException('Pla de sistema no trobat.');
        }

        // Càlcul de consums actius i en custòdia en fred
        $activeItemsCount = $itemRepository->countActiveByOwner($user);
        $frozenCount = $itemRepository->countFrozenByOwner($user);

        $maxActive = $currentPlan->getMaxActiveItems();
        $itemsPct = $maxActive > 0 ? (int) round(($activeItemsCount / $maxActive) * 100) : 0;
        if ($itemsPct > 100) {
            $itemsPct = 100;
        }

        $aiUsed = $user->getAiUsageCount();
        $maxAi = $currentPlan->getAiAssistanceQuota();
        $aiPct = $maxAi > 0 ? (int) round(($aiUsed / $maxAi) * 100) : 0;
        if ($aiPct > 100) {
            $aiPct = 100;
        }

        // Recuperar tots els plans per a la comparativa d'upgrade (actius i en preparació)
        $allPlans = $planRepository->findBy([], ['priceCents' => 'ASC']);

        return $this->render('plan/index.html.twig', [
            'user' => $user,
            'currentPlan' => $currentPlan,
            'activeItemsCount' => $activeItemsCount,
            'frozenCount' => $frozenCount,
            'maxActive' => $maxActive,
            'itemsPct' => $itemsPct,
            'aiUsed' => $aiUsed,
            'maxAi' => $maxAi,
            'aiPct' => $aiPct,
            'allPlans' => $allPlans,
        ]);
    }

    #[Route('/app/perfil/facturacio', name: 'app_plan_billing', methods: ['GET'])]
    #[Route('/app/plan/billing', name: 'app_plan_billing_legacy', methods: ['GET'])]
    public function billing(
        PlanRepository $planRepository,
        PaymentTransactionRepository $transactionRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $currentPlan = $user->getPlan() ?? $planRepository->findByCode('free');
        if ($currentPlan === null) {
            throw $this->createNotFoundException('Pla de sistema no trobat.');
        }

        $transactions = $transactionRepository->findByUser($user, 25);
        $freePlan = $planRepository->findByCode('free');
        $plusPlan = $planRepository->findByCode('plus');

        return $this->render('plan/billing.html.twig', [
            'user' => $user,
            'currentPlan' => $currentPlan,
            'freePlan' => $freePlan,
            'plusPlan' => $plusPlan,
            'maxFreeItems' => $freePlan?->getMaxActiveItems() ?? 20,
            'maxPlusItems' => $plusPlan?->getMaxActiveItems() ?? 100,
            'transactions' => $transactions,
        ]);
    }
}
