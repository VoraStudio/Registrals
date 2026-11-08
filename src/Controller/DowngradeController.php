<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\ItemRepository;
use App\Service\Plan\PlanDowngradeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/downgrade', name: 'app_downgrade_')]
#[IsGranted('ROLE_USER')]
class DowngradeController extends AbstractController
{
    #[Route('/adequacio', name: 'adequacy', methods: ['GET', 'POST'])]
    public function adequacy(
        Request $request,
        ItemRepository $itemRepository,
        \App\Repository\PlanRepository $planRepository,
        PlanDowngradeService $downgradeService
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $allItems = $itemRepository->findAllForDowngradeSelection($user);
        $freePlan = $planRepository->findByCode('free');
        $plusPlan = $planRepository->findByCode('plus');
        $maxFreeItems = $user->getPlan()?->getMaxActiveItems() ?? $freePlan?->getMaxActiveItems() ?? PlanDowngradeService::DEFAULT_FREE_LIMIT;
        if ($maxFreeItems <= 0) {
            $maxFreeItems = PlanDowngradeService::DEFAULT_FREE_LIMIT;
        }

        // Si l'usuari no supera el límit del pla, no requereix adequació
        if (count($allItems) <= $maxFreeItems) {
            $this->addFlash('info', 'El teu patrimoni no supera el límit de béns actius del teu pla.');
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('downgrade_adequacy', $submittedToken)) {
                $this->addFlash('error', 'Token de seguretat invàlid. Si us plau, torna-ho a provar.');
                return $this->redirectToRoute('app_downgrade_adequacy');
            }

            /** @var list<string> $rawSelectedIds */
            $rawSelectedIds = $request->request->all('selected_items');
            $selectedIds = array_map(intval(...), $rawSelectedIds);

            if (count($selectedIds) > $maxFreeItems) {
                $this->addFlash('error', sprintf('No pots seleccionar més de %d béns actius. En tens seleccionats %d.', $maxFreeItems, count($selectedIds)));
            } else {
                $success = $downgradeService->selectActiveItems($user, $selectedIds);
                if ($success) {
                    $this->addFlash('success', sprintf(
                        'S\'ha desat la teva selecció de patrimoni: %d béns actius i %d en custòdia en fred protegida.',
                        count($selectedIds),
                        count($allItems) - count($selectedIds)
                    ));
                    return $this->redirectToRoute('app_dashboard');
                }

                $this->addFlash('error', 'S\'ha produït un error en desar la selecció. Verifica el nombre de béns.');
            }
        }

        return $this->render('downgrade/adequacy.html.twig', [
            'user' => $user,
            'items' => $allItems,
            'maxFreeItems' => $maxFreeItems,
            'maxPlusItems' => $plusPlan?->getMaxActiveItems() ?? 100,
            'plusPlan' => $plusPlan,
            'inGracePeriod' => $user->isInDowngradeGracePeriod(),
            'graceDaysLeft' => $user->getDowngradeGraceDaysLeft(),
            'graceExpiresAt' => $user->getDowngradeGracePeriodExpiresAt(),
        ]);
    }
}
