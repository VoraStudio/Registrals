<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app', name: 'app_')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user && !$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding_welcome');
        }

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
        ]);
    }
}

