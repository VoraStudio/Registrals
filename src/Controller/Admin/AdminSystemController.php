<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Repository\UserActivityLogRepository;
use App\Repository\SystemSettingRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminSystemController extends AbstractController
{
    #[Route('/serveis', name: 'app_admin_services', methods: ['GET'])]
    public function services(
        ManagerRegistry $doctrine,
        SystemSettingRepository $settingRepository,
        HttpClientInterface $httpClient
    ): Response {
        // 1. Comprovació MySQL DB 'default' (registrals)
        $defaultDbStatus = false;
        $defaultDbError = null;
        try {
            $conn = $doctrine->getConnection('default');
            $defaultDbStatus = $conn->executeQuery('SELECT 1')->fetchOne() === 1;
        } catch (\Throwable $e) {
            $defaultDbError = $e->getMessage();
        }

        // 2. Comprovació MySQL DB 'audit' (registrals_audit)
        $auditDbStatus = false;
        $auditDbError = null;
        try {
            $connAudit = $doctrine->getConnection('audit');
            $auditDbStatus = $connAudit->executeQuery('SELECT 1')->fetchOne() === 1;
        } catch (\Throwable $e) {
            $auditDbError = $e->getMessage();
        }

        // 3. Comprovació Node SGLang Local (Qwen-VL)
        $aiGlobalEnabled = $settingRepository->getBool('ai_global_enabled', true);
        $sglangStatus = false;
        $sglangLatency = null;
        try {
            $start = microtime(true);
            $res = $httpClient->request('GET', 'http://127.0.0.1:30000/v1/models', ['timeout' => 1.5]);
            if ($res->getStatusCode() === 200) {
                $sglangStatus = true;
                $sglangLatency = round((microtime(true) - $start) * 1000);
            }
        } catch (\Throwable) {
            $sglangStatus = false;
        }

        // 4. Emmagatzematge Flysystem privat
        $storageDir = $this->getParameter('kernel.project_dir') . '/var/storage';
        $diskFree = @disk_free_space($storageDir);
        $diskTotal = @disk_total_space($storageDir);

        return $this->render('admin/system/services.html.twig', [
            'phpVersion' => PHP_VERSION,
            'symfonyVersion' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            'defaultDbStatus' => $defaultDbStatus,
            'defaultDbError' => $defaultDbError,
            'auditDbStatus' => $auditDbStatus,
            'auditDbError' => $auditDbError,
            'sglangStatus' => $sglangStatus,
            'sglangLatency' => $sglangLatency,
            'aiGlobalEnabled' => $aiGlobalEnabled,
            'diskFree' => $diskFree ?: 0,
            'diskTotal' => $diskTotal ?: 1,
        ]);
    }

    #[Route('/logs', name: 'app_admin_audit_logs', methods: ['GET'])]
    public function auditLogs(
        Request $request,
        UserActivityLogRepository $logRepository
    ): Response {
        $search = $request->query->get('q');
        $action = $request->query->get('accio');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 25);
        if (!in_array($limit, [10, 25, 50], true)) {
            $limit = 25;
        }

        $logs = $logRepository->findPaginatedForAdmin($search, $action, $page, $limit);
        $totalLogs = $logRepository->countForAdmin($search, $action);
        $totalPages = max(1, (int) ceil($totalLogs / $limit));

        return $this->render('admin/system/logs.html.twig', [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'currentSearch' => $search,
            'currentAction' => $action,
            'currentLimit' => $limit,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'actions' => UserActivityAction::cases(),
        ]);
    }
}
