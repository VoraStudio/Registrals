<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ItemDocumentRepository;
use App\Repository\ItemRepository;
use App\Repository\PlanRepository;
use App\Repository\SystemSettingRepository;
use App\Repository\UserRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Service\GeoIp\GeoLocationService;

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
        SystemSettingRepository $settingRepository,
        ManagerRegistry $doctrine,
        HttpClientInterface $httpClient,
        GeoLocationService $geoLocationService
    ): Response {
        // 1. Mètriques de Negoci & Activitat
        $totalUsers = $userRepository->countTotal();
        $activeUsers = $userRepository->countActive();
        $blockedUsers = $userRepository->countBlocked();
        $usersByPlan = $userRepository->countUsersByPlan();

        $totalItems = $itemRepository->countTotal();
        $activeItems = $itemRepository->countActiveTotal();

        $totalDocuments = $documentRepository->countTotal();
        $totalBytes = $documentRepository->sumTotalBytes();

        // 2. Mètriques de Salut Tècnica del Sistema
        $mysqlOk = false;
        try {
            $mysqlOk = $doctrine->getConnection('default')->executeQuery('SELECT 1')->fetchOne() === 1;
        } catch (\Throwable) {
            $mysqlOk = false;
        }

        $auditDbOk = false;
        try {
            $auditDbOk = $doctrine->getConnection('audit')->executeQuery('SELECT 1')->fetchOne() === 1;
        } catch (\Throwable) {
            $auditDbOk = false;
        }

        $aiGlobalEnabled = $settingRepository->getBool('ai_global_enabled', true);
        $sglangOk = false;
        try {
            $res = $httpClient->request('GET', 'http://127.0.0.1:30000/v1/models', ['timeout' => 1.0]);
            $sglangOk = $res->getStatusCode() === 200;
        } catch (\Throwable) {
            $sglangOk = false;
        }

        // 3. Conversions & Ràtios d'Upgrade de Plans
        $freeUsers = $usersByPlan['free'] ?? 0;
        $plusUsers = $usersByPlan['plus'] ?? 0;
        $unlimitedUsers = $usersByPlan['unlimited'] ?? 0;

        $rateFreeToPlus = $totalUsers > 0 ? round(($plusUsers / $totalUsers) * 100, 1) : 0.0;
        $ratePlusToUnlimited = ($plusUsers + $unlimitedUsers) > 0 ? round(($unlimitedUsers / ($plusUsers + $unlimitedUsers)) * 100, 1) : 0.0;

        // 4. Estimacions Econòmiques (ARR i Marge)
        $estimatedArr = ($plusUsers * 22) + ($unlimitedUsers * 59);
        $estimatedMarginPercent = 88;

        // 5. Canals d'Accés & Registre (Email vs Google OAuth)
        $googleUsers = 0;
        try {
            $googleUsers = (int) $doctrine->getConnection('default')
                ->executeQuery("SELECT COUNT(*) FROM users WHERE auth_provider = 'google' OR google_id IS NOT NULL")
                ->fetchOne();
        } catch (\Throwable) {
            $googleUsers = 0;
        }
        $emailUsers = max(0, $totalUsers - $googleUsers);
        $percentGoogle = $totalUsers > 0 ? round(($googleUsers / $totalUsers) * 100, 1) : 0.0;
        $percentEmail = $totalUsers > 0 ? round(($emailUsers / $totalUsers) * 100, 1) : 0.0;

        // 6. Demanda & Smoke Tests (Apple ID Leads i Password Reset)
        $appleLoginClicks = 0;
        $appleRegisterClicks = 0;
        $passwordResetClicks = 0;
        try {
            $interestRows = $doctrine->getConnection('default')
                ->executeQuery("SELECT feature_code, origin, COUNT(*) as cnt FROM feature_interest_events GROUP BY feature_code, origin")
                ->fetchAllAssociative();

            foreach ($interestRows as $row) {
                $feature = (string) $row['feature_code'];
                $origin = (string) $row['origin'];
                $cnt = (int) $row['cnt'];

                if ($feature === 'apple_id') {
                    if ($origin === 'login_button') {
                        $appleLoginClicks += $cnt;
                    } elseif ($origin === 'register_button') {
                        $appleRegisterClicks += $cnt;
                    }
                } elseif ($feature === 'password_reset') {
                    $passwordResetClicks += $cnt;
                }
            }
        } catch (\Throwable) {
            // Taula no disponible
        }
        $totalAppleClicks = $appleLoginClicks + $appleRegisterClicks;

        // 7. Distribució Geogràfica de Trànsit (Google GeoChart)
        $ipRows = [];
        try {
            // Logs d'auditoria (registrals_audit)
            $auditConn = $doctrine->getConnection('audit');
            $auditIps = $auditConn->executeQuery("SELECT ip_address, COUNT(*) as cnt FROM user_activity_logs WHERE ip_address IS NOT NULL GROUP BY ip_address")->fetchAllAssociative();
            $ipRows = array_merge($ipRows, $auditIps);
        } catch (\Throwable) {}

        try {
            // Esdeveniments d'interès (registrals)
            $defaultConn = $doctrine->getConnection('default');
            $featureIps = $defaultConn->executeQuery("SELECT ip_address, COUNT(*) as cnt FROM feature_interest_events WHERE ip_address IS NOT NULL GROUP BY ip_address")->fetchAllAssociative();
            $ipRows = array_merge($ipRows, $featureIps);
        } catch (\Throwable) {}

        $geoData = $geoLocationService->aggregateGeoTraffic($ipRows);

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
            'mysqlOk' => $mysqlOk,
            'auditDbOk' => $auditDbOk,
            'sglangOk' => $sglangOk,
            'aiGlobalEnabled' => $aiGlobalEnabled,
            'freeUsers' => $freeUsers,
            'plusUsers' => $plusUsers,
            'unlimitedUsers' => $unlimitedUsers,
            'rateFreeToPlus' => $rateFreeToPlus,
            'ratePlusToUnlimited' => $ratePlusToUnlimited,
            'estimatedArr' => $estimatedArr,
            'estimatedMarginPercent' => $estimatedMarginPercent,
            'recentUsers' => $recentUsers,
            'recentItems' => $recentItems,
            'plans' => $plans,
            'googleUsers' => $googleUsers,
            'emailUsers' => $emailUsers,
            'percentGoogle' => $percentGoogle,
            'percentEmail' => $percentEmail,
            'totalAppleClicks' => $totalAppleClicks,
            'appleLoginClicks' => $appleLoginClicks,
            'appleRegisterClicks' => $appleRegisterClicks,
            'passwordResetClicks' => $passwordResetClicks,
            'geoChartData' => $geoData['chartData'],
            'topCountries' => $geoData['topCountries'],
            'totalGeoRequests' => $geoData['totalRequests'],
        ]);
    }
}
