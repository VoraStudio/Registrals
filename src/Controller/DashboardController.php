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
    public function index(
        \Symfony\Component\HttpFoundation\Request $request,
        \App\Repository\ItemRepository $itemRepository,
        \App\Repository\CategoryRepository $categoryRepository,
        \App\Repository\ItemTransferRepository $itemTransferRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Els administradors van directament a la cabina de gestió Notarial / Admin
        if ($this->isGranted('ROLE_ADMIN') && !$request->query->has('client_view')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($user && !$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding_welcome');
        }

        $items = $itemRepository->findTopLevelItemsByOwner($user);
        $categories = $categoryRepository->findRootCategories();
        $pendingIncomingTransfers = $itemTransferRepository->findPendingReceivedByEmail($user->getEmail());

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'items' => $items,
            'categories' => $categories,
            'pendingIncomingTransfers' => $pendingIncomingTransfers,
        ]);
    }
}

