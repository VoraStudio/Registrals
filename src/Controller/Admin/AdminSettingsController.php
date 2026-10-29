<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SystemSettingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/configuracio')]
#[IsGranted('ROLE_ADMIN')]
class AdminSettingsController extends AbstractController
{
    #[Route('', name: 'app_admin_settings', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SystemSettingRepository $settingRepository,
        CsrfTokenManagerInterface $csrfTokenManager
    ): Response {
        if ($request->isMethod('POST')) {
            $token = new CsrfToken('admin_settings_update', (string) $request->request->get('_csrf_token'));
            if (!$csrfTokenManager->isTokenValid($token)) {
                $this->addFlash('error', 'Token de seguretat CSRF no vàlid.');
                return $this->redirectToRoute('app_admin_settings');
            }

            // 1. Feature Toggle Global d'IA
            $aiGlobal = $request->request->has('ai_global_enabled') ? '1' : '0';
            $settingRepository->set('ai_global_enabled', $aiGlobal, 'Interruptor global d\'IA Sobirana (SGLang Qwen VL)');

            // 2. Àmbit Geogràfic i Països admesos
            $allowedCountries = trim((string) $request->request->get('allowed_countries', 'ES,AD,FR,IT,PT,DE'));
            $settingRepository->set('allowed_countries', $allowedCountries, 'Països admesos per a la contractació de plans Plus/Unlimited');

            // 3. Moneda ISO per defecte
            $defaultCurrency = trim((string) $request->request->get('default_currency', 'EUR'));
            $settingRepository->set('default_currency', $defaultCurrency, 'Moneda ISO oficial de la plataforma');

            // 4. URL de l'Endpoint SGLang Local
            $sglangEndpoint = trim((string) $request->request->get('ai_sglang_endpoint', 'http://127.0.0.1:30000/v1'));
            $settingRepository->set('ai_sglang_endpoint', $sglangEndpoint, 'Endpoint local del node SGLang d\'IA');

            $this->addFlash('success', 'La configuració del sistema i de la IA sobirana s\'ha desat correctament.');

            return $this->redirectToRoute('app_admin_settings');
        }

        $aiGlobalEnabled = $settingRepository->getBool('ai_global_enabled', true);
        $allowedCountries = $settingRepository->get('allowed_countries', 'ES,AD,FR,IT,PT,DE');
        $defaultCurrency = $settingRepository->get('default_currency', 'EUR');
        $sglangEndpoint = $settingRepository->get('ai_sglang_endpoint', 'http://127.0.0.1:30000/v1');

        return $this->render('admin/settings/index.html.twig', [
            'aiGlobalEnabled' => $aiGlobalEnabled,
            'allowedCountries' => $allowedCountries,
            'defaultCurrency' => $defaultCurrency,
            'sglangEndpoint' => $sglangEndpoint,
        ]);
    }
}
