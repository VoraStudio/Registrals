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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

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

        // 5. Cues Asíncrones i Gestió d'Errors (Symfony Messenger)
        $messengerData = $this->getMessengerData($doctrine);

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
            'messengerStats' => $messengerData['stats'],
            'failedMessages' => $messengerData['failedMessages'],
        ]);
    }

    #[Route('/serveis/messenger/retry/{id}', name: 'app_admin_messenger_retry', methods: ['POST'])]
    public function retryMessengerMessage(
        int $id,
        Request $request,
        KernelInterface $kernel
    ): Response {
        if (!$this->isCsrfTokenValid('messenger_retry_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat CSRF invàlid.');
            return $this->redirectToRoute('app_admin_services');
        }

        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $input = new ArrayInput([
                'command' => 'messenger:failed:retry',
                'id' => [(string) $id],
                '--force' => true,
                '--transport' => 'failed',
            ]);
            $output = new BufferedOutput();
            $exitCode = $application->run($input, $output);

            if ($exitCode === 0) {
                $this->addFlash('success', "Missatge fallit #{$id} reencuat correctament a la seva cua d'origen.");
            } else {
                $this->addFlash('error', "No s'ha pogut reintentar el missatge #{$id}: " . $output->fetch());
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', "Error crític en reintentar el missatge #{$id}: " . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_services');
    }

    #[Route('/serveis/messenger/remove/{id}', name: 'app_admin_messenger_remove', methods: ['POST'])]
    public function removeMessengerMessage(
        int $id,
        Request $request,
        KernelInterface $kernel
    ): Response {
        if (!$this->isCsrfTokenValid('messenger_remove_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat CSRF invàlid.');
            return $this->redirectToRoute('app_admin_services');
        }

        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $input = new ArrayInput([
                'command' => 'messenger:failed:remove',
                'id' => [(string) $id],
                '--force' => true,
                '--transport' => 'failed',
            ]);
            $output = new BufferedOutput();
            $exitCode = $application->run($input, $output);

            if ($exitCode === 0) {
                $this->addFlash('success', "Missatge fallit #{$id} suprimit i descartat amb èxit.");
            } else {
                $this->addFlash('error', "No s'ha pogut descartar el missatge #{$id}: " . $output->fetch());
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', "Error en descartar el missatge #{$id}: " . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_services');
    }

    #[Route('/serveis/messenger/retry-all', name: 'app_admin_messenger_retry_all', methods: ['POST'])]
    public function retryAllMessengerMessages(
        Request $request,
        ManagerRegistry $doctrine,
        KernelInterface $kernel
    ): Response {
        if (!$this->isCsrfTokenValid('messenger_retry_all', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat CSRF invàlid.');
            return $this->redirectToRoute('app_admin_services');
        }

        try {
            $conn = $doctrine->getConnection('default');
            $ids = $conn->executeQuery("SELECT id FROM messenger_messages WHERE queue_name = 'failed'")->fetchFirstColumn();
            if (empty($ids)) {
                $this->addFlash('info', "No hi ha missatges pendents a la cua d'errors.");
                return $this->redirectToRoute('app_admin_services');
            }

            $application = new Application($kernel);
            $application->setAutoExit(false);
            $input = new ArrayInput([
                'command' => 'messenger:failed:retry',
                'id' => array_map('strval', $ids),
                '--force' => true,
                '--transport' => 'failed',
            ]);
            $output = new BufferedOutput();
            $exitCode = $application->run($input, $output);

            if ($exitCode === 0) {
                $this->addFlash('success', sprintf("%d missatges reencuats amb èxit.", count($ids)));
            } else {
                $this->addFlash('error', "Incidència en reintentar els missatges: " . $output->fetch());
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', "Error en reintentar tots els missatges: " . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_services');
    }

    #[Route('/serveis/messenger/remove-all', name: 'app_admin_messenger_remove_all', methods: ['POST'])]
    public function removeAllMessengerMessages(
        Request $request,
        KernelInterface $kernel
    ): Response {
        if (!$this->isCsrfTokenValid('messenger_remove_all', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat CSRF invàlid.');
            return $this->redirectToRoute('app_admin_services');
        }

        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $input = new ArrayInput([
                'command' => 'messenger:failed:remove',
                '--all' => true,
                '--force' => true,
                '--transport' => 'failed',
            ]);
            $output = new BufferedOutput();
            $exitCode = $application->run($input, $output);

            if ($exitCode === 0) {
                $this->addFlash('success', "Tots els missatges fallits de la cua s'han suprimit amb èxit.");
            } else {
                $this->addFlash('error', "No s'han pogut descartar els missatges: " . $output->fetch());
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', "Error en buidar la cua d'errors: " . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_services');
    }

    /**
     * @return array{
     *     stats: array<string, int>,
     *     failedMessages: list<array{
     *         id: int,
     *         type: ?string,
     *         shortType: string,
     *         errorMessage: string,
     *         exceptionClass: ?string,
     *         retries: int,
     *         createdAt: \DateTimeImmutable
     *     }>
     * }
     */
    private function getMessengerData(ManagerRegistry $doctrine): array
    {
        $stats = [
            'async' => 0,
            'async_ai' => 0,
            'async_chain' => 0,
            'failed' => 0,
            'total_pending' => 0,
        ];
        $failedMessages = [];

        try {
            $conn = $doctrine->getConnection('default');

            // 1. Estadístiques per cua
            $rows = $conn->executeQuery('SELECT queue_name, COUNT(*) as cnt FROM messenger_messages GROUP BY queue_name')->fetchAllAssociative();
            foreach ($rows as $row) {
                $q = (string) $row['queue_name'];
                $c = (int) $row['cnt'];
                $stats[$q] = $c;
                if ($q !== 'failed') {
                    $stats['total_pending'] += $c;
                }
            }

            // 2. Llistat detallat de missatges a la cua d'errors (failed)
            if (($stats['failed'] ?? 0) > 0) {
                $failedRows = $conn->executeQuery("SELECT id, body, headers, queue_name, created_at, available_at FROM messenger_messages WHERE queue_name = 'failed' ORDER BY id DESC LIMIT 20")->fetchAllAssociative();

                foreach ($failedRows as $row) {
                    $headers = json_decode($row['headers'] ?? '[]', true) ?: [];
                    $type = $headers['type'] ?? null;
                    if (!$type && preg_match('/App\\\\Message\\\\[A-Za-z0-9_]+/', (string) $row['body'], $matches)) {
                        $type = $matches[0];
                    }
                    $shortType = $type ? substr(strrchr($type, '\\'), 1) : 'Missatge Asíncron';

                    $errorMessage = "Error en l'execució del missatge";
                    $exceptionClass = null;
                    $retries = 0;

                    // ErrorDetailsStamp
                    if (isset($headers['X-Message-Stamp-Symfony\Component\Messenger\Stamp\ErrorDetailsStamp'])) {
                        $errorStamp = $headers['X-Message-Stamp-Symfony\Component\Messenger\Stamp\ErrorDetailsStamp'];
                        if (is_array($errorStamp) && isset($errorStamp[0]['exceptionMessage'])) {
                            $errorMessage = (string) $errorStamp[0]['exceptionMessage'];
                            $exceptionClass = $errorStamp[0]['exceptionClass'] ?? null;
                        }
                    }

                    // RedeliveryStamp
                    if (isset($headers['X-Message-Stamp-Symfony\Component\Messenger\Stamp\RedeliveryStamp'])) {
                        $redelivery = $headers['X-Message-Stamp-Symfony\Component\Messenger\Stamp\RedeliveryStamp'];
                        if (is_array($redelivery) && isset($redelivery[0]['retryCount'])) {
                            $retries = (int) $redelivery[0]['retryCount'];
                        }
                    }

                    $failedMessages[] = [
                        'id' => (int) $row['id'],
                        'type' => $type,
                        'shortType' => $shortType,
                        'errorMessage' => $errorMessage,
                        'exceptionClass' => $exceptionClass ? substr(strrchr($exceptionClass, '\\'), 1) : null,
                        'retries' => $retries,
                        'createdAt' => new \DateTimeImmutable((string) $row['created_at']),
                    ];
                }
            }
        } catch (\Throwable) {
            // Taula no disponible o error de lectura
        }

        return [
            'stats' => $stats,
            'failedMessages' => $failedMessages,
        ];
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
            'groupedActions' => UserActivityAction::groupedByCategory(),
        ]);
    }
}
