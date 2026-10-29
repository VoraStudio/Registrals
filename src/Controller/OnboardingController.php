<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class OnboardingController extends AbstractController
{
    #[Route('/onboarding/welcome', name: 'app_onboarding_welcome')]
    public function welcome(PlanRepository $planRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Els administradors no requereixen onboarding i van directament al panell d'administració
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Si ja ha superat l'onboarding, va directament al Dashboard
        if ($user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $plans = $planRepository->findAll();

        return $this->render('onboarding/welcome.html.twig', [
            'user' => $user,
            'plans' => $plans,
        ]);
    }

    #[Route('/onboarding/complete', name: 'app_onboarding_complete', methods: ['POST'])]
    public function complete(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setOnboardingCompleted(true);
        $entityManager->flush();

        $this->addFlash('success', 'Benvingut/da a Registrals! El teu compte s\'ha configurat amb el Pla Free.');

        return $this->redirectToRoute('app_dashboard');
    }
}
