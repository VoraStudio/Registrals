<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Repository\PlanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PlanController extends AbstractController
{
    #[Route('/app/plan', name: 'app_plan', methods: ['GET'])]
    public function index(PlanRepository $planRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $currentPlan = $user->getPlan();

        // Càlcul de consums actius
        $activeItemsCount = $user->getItems()->filter(function (Item $item): bool {
            return $item->getStatus()->value !== 'archived';
        })->count();

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

        // Recuperar tots els plans per a la comparativa d'upgrade
        $allPlans = $planRepository->findBy(['isActive' => true], ['priceCents' => 'ASC']);

        return $this->render('plan/index.html.twig', [
            'user' => $user,
            'currentPlan' => $currentPlan,
            'activeItemsCount' => $activeItemsCount,
            'maxActive' => $maxActive,
            'itemsPct' => $itemsPct,
            'aiUsed' => $aiUsed,
            'maxAi' => $maxAi,
            'aiPct' => $aiPct,
            'allPlans' => $allPlans,
        ]);
    }
}
