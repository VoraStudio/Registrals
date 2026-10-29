<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Plan;
use App\Form\Admin\PlanType;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/plans')]
#[IsGranted('ROLE_ADMIN')]
class AdminPlanController extends AbstractController
{
    #[Route('', name: 'app_admin_plans')]
    public function index(PlanRepository $planRepository): Response
    {
        $plans = $planRepository->findAll();

        return $this->render('admin/plan/index.html.twig', [
            'plans' => $plans,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_plan_edit', methods: ['GET', 'POST'])]
    public function edit(
        Plan $plan,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(PlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', sprintf('Els paràmetres del pla "%s" s\'han actualitzat correctament en calent.', $plan->getName()));

            return $this->redirectToRoute('app_admin_plans');
        }

        return $this->render('admin/plan/edit.html.twig', [
            'plan' => $plan,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-ai', name: 'app_admin_plan_toggle_ai', methods: ['POST'])]
    public function toggleAi(
        Plan $plan,
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        $token = new CsrfToken('toggle_plan_ai_' . $plan->getId(), (string) $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Token de seguretat no vàlid.');
            return $this->redirectToRoute('app_admin_plans');
        }

        $plan->setAiAssistanceEnabled(!$plan->isAiAssistanceEnabled());
        $em->flush();

        $stateText = $plan->isAiAssistanceEnabled() ? 'habilitada' : 'deshabilitada';
        $this->addFlash('success', sprintf('L\'assistència d\'IA pel pla "%s" ha estat %s.', $plan->getName(), $stateText));

        return $this->redirectToRoute('app_admin_plans');
    }
}
