<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Repository\UserActivityLogRepository;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use App\Enum\ItemStatus;
use App\Repository\ItemRepository;
use App\Repository\PlanRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/usuaris')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    private const USERS_PER_PAGE = 20;

    #[Route('', name: 'app_admin_users')]
    public function index(
        Request $request,
        UserRepository $userRepository,
        PlanRepository $planRepository
    ): Response {
        $query = $request->query->get('q');
        $planCode = $request->query->get('pla');
        $status = $request->query->get('estat');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = $request->query->getInt('limit', 10);
        if (!in_array($limit, [10, 25, 50], true)) {
            $limit = 10;
        }

        $offset = ($page - 1) * $limit;
        $users = $userRepository->searchAndFilter($query, $planCode, $status, $limit, $offset);
        $totalMatches = $userRepository->countSearchAndFilter($query, $planCode, $status);
        $totalPages = max(1, (int) ceil($totalMatches / $limit));

        $plans = $planRepository->findAll();

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'plans' => $plans,
            'query' => $query,
            'selectedPlan' => $planCode,
            'selectedStatus' => $status,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalMatches' => $totalMatches,
            'currentLimit' => $limit,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'app_admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserActivityLogger $activityLogger
    ): Response {
        $token = new CsrfToken('toggle_user_status_' . $user->getId(), (string) $request->request->get('_csrf_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'El testimoni de seguretat CSRF no és vàlid.');
            return $this->redirectToRoute('app_admin_users');
        }

        // Evitar que un administrador es bloquegi a si mateix
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'No pots suspendre el teu propi compte d\'administrador.');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($user->isActive()) {
            $reason = trim((string) $request->request->get('block_reason', 'Suspensió administrativa per incompliment de condicions d\'ús.'));
            $user->setIsActive(false);
            $user->setBlockedAt(new \DateTimeImmutable());
            $user->setBlockReason($reason !== '' ? $reason : 'Suspensió preventiva per administració.');
            $this->addFlash('success', sprintf('El compte de %s ha estat suspès.', $user->getEmail()));

            $activityLogger->log(
                user: $user,
                action: UserActivityAction::STATUS_CHANGED,
                description: 'Compte suspès per l\'administració',
                details: ['reason' => $user->getBlockReason(), 'admin' => $this->getUser()?->getUserIdentifier()]
            );
        } else {
            $user->setIsActive(true);
            $user->setBlockedAt(null);
            $user->setBlockReason(null);
            $this->addFlash('success', sprintf('El compte de %s ha estat reactivat amb èxit.', $user->getEmail()));

            $activityLogger->log(
                user: $user,
                action: UserActivityAction::STATUS_CHANGED,
                description: 'Compte reactivat per l\'administració',
                details: ['admin' => $this->getUser()?->getUserIdentifier()]
            );
        }

        $em->flush();

        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/admin/usuaris')) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/{id}', name: 'app_admin_user_detail', methods: ['GET'])]
    public function detail(
        User $user,
        Request $request,
        ItemRepository $itemRepository,
        UserActivityLogRepository $activityLogRepository
    ): Response {
        $search = $request->query->get('q');
        $status = $request->query->get('status');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        if (!in_array($limit, [10, 25, 50], true)) {
            $limit = 10;
        }

        $items = $itemRepository->findPaginatedForAdmin($search, $status, $page, $limit, $user);
        $totalItems = $itemRepository->countForAdmin($search, $status, $user);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        $recentLogs = $activityLogRepository->findRecentByUserUuid($user->getUuid()->toRfc4122(), 5);

        return $this->render('admin/user/detail.html.twig', [
            'user' => $user,
            'items' => $items,
            'totalItems' => $totalItems,
            'currentSearch' => $search,
            'currentStatus' => $status,
            'currentLimit' => $limit,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'statuses' => ItemStatus::cases(),
            'recentLogs' => $recentLogs,
        ]);
    }
}
