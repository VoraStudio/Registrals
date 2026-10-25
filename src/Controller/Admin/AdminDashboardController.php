<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ItemDocumentRepository;
use App\Repository\ItemRepository;
use App\Repository\PlanRepository;
use App\Repository\SystemSettingRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function index(
        UserRepository $userRepository,
        ItemRepository $itemRepository,
        ItemDocumentRepository $documentRepository,
        PlanRepository $planRepository,
        SystemSettingRepository $settingRepository
    ): Response {
        $totalUsers = $userRepository->countTotal();
        $activeUsers = $userRepository->countActive();
        $blockedUsers = $userRepository->countBlocked();
        $usersByPlan = $userRepository->countUsersByPlan();

        $totalItems = $itemRepository->countTotal();
        $activeItems = $itemRepository->countActiveTotal();

        $totalDocuments = $documentRepository->countTotal();
        $totalBytes = $documentRepository->sumTotalBytes();

        $aiGlobalEnabled = $settingRepository->getBool('ai_global_enabled', true);
        $recentUsers = $userRepository->findRecent(6);
        $recentItems = $itemRepository->findRecent(6);
        $plans = $planRepository->findAll();

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'blockedUsers' => $blockedUsers,
            'usersByPlan' => $usersByPlan,
            'totalItems' => $totalItems,
            'activeItems' => $activeItems,
            'totalDocuments' => $totalDocuments,
            'totalBytes' => $totalBytes,
            'aiGlobalEnabled' => $aiGlobalEnabled,
            'recentUsers' => $recentUsers,
            'recentItems' => $recentItems,
            'plans' => $plans,
        ]);
    }
}
