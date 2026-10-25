<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
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

        $offset = ($page - 1) * self::USERS_PER_PAGE;
        $users = $userRepository->searchAndFilter($query, $planCode, $status, self::USERS_PER_PAGE, $offset);
        $totalMatches = $userRepository->countSearchAndFilter($query, $planCode, $status);
        $totalPages = max(1, (int) ceil($totalMatches / self::USERS_PER_PAGE));

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
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'app_admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager
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
        } else {
            $user->setIsActive(true);
            $user->setBlockedAt(null);
            $user->setBlockReason(null);
            $this->addFlash('success', sprintf('El compte de %s ha estat reactivat amb èxit.', $user->getEmail()));
        }

        $em->flush();

        $referer = $request->headers->get('referer');
        if ($referer && str_contains($referer, '/admin/usuaris')) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/{id}', name: 'app_admin_user_detail', methods: ['GET'])]
    public function detail(User $user): Response
    {
        return $this->render('admin/user/detail.html.twig', [
            'user' => $user,
        ]);
    }
}
