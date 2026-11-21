<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public website pages (migrated 1:1 from the original static site).
 */
final class PageController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET', 'HEAD'])]
    public function home(
        \Symfony\Component\HttpFoundation\Request $request,
        \App\Service\PlatformStatsService $statsService,
        \App\Repository\PlanRepository $planRepository,
        \App\Repository\FaqItemRepository $faqRepository
    ): Response {
        // 1. Si ja té sessió activa, entra directament al dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // 2. Si el dispositiu està reconegut, salta la landing i va al desbloqueig ràpid
        if ($request->cookies->has(\App\EventSubscriber\DeviceRecognitionSubscriber::COOKIE_NAME)) {
            return $this->redirectToRoute('app_login');
        }

        // 3. Tots els plans (incloent Properament/inactius) parametritzats des de backoffice
        $plans = $this->getPlansByCode($planRepository);

        // 4. Preguntes freqüents des de base de dades (fallback a JSON si és buida)
        $dbFaqs = $faqRepository->findActiveOrdered();
        $faqs = $dbFaqs !== []
            ? array_map(static fn ($f): array => ['question' => $f->getQuestion(), 'answer' => $f->getAnswer()], $dbFaqs)
            : $this->loadFaqs('ca');

        // 5. Visitant nou o sessió desconeguda: mostra la Landing Page corporativa amb mètriques reals
        return $this->render('page/home.html.twig', [
            'stats' => $statsService->getHomeStats(),
            'plans' => $plans,
            'faqs' => $faqs,
        ]);
    }

    #[Route('/publicitat', name: 'app_publicitat', methods: ['GET', 'HEAD'])]
    public function publicitat(\App\Repository\AdCampaignRepository $adRepository): Response
    {
        return $this->render('page/publicitat.html.twig', [
            'campaigns' => $adRepository->findCurrentlyActive(),
        ]);
    }

    #[Route('/legal', name: 'app_legal', methods: ['GET', 'HEAD'])]
    public function legal(): Response
    {
        return $this->render('page/legal.html.twig');
    }

    #[Route('/legal/ia-sobirana', name: 'app_legal_ai_sovereign', methods: ['GET', 'HEAD'])]
    public function aiTransparency(): Response
    {
        return $this->render('legal/ia_sobirana.html.twig');
    }

    #[Route('/producte', name: 'app_producte', methods: ['GET', 'HEAD'])]
    public function producte(): Response
    {
        return $this->render('page/producte.html.twig');
    }

    #[Route('/properament', name: 'app_holding', methods: ['GET', 'HEAD'])]
    public function holding(): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/holding/index.html';
        $html = file_exists($filePath) ? (string) file_get_contents($filePath) : 'Properament';

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    #[Route('/sobre-nosaltres', name: 'app_about', methods: ['GET', 'HEAD'])]
    public function about(): Response
    {
        return $this->render('page/about.html.twig');
    }

    #[Route('/histories', name: 'app_histories', methods: ['GET', 'HEAD'])]
    public function histories(): Response
    {
        return $this->render('page/histories.html.twig');
    }

    #[Route('/premsa', name: 'app_press', methods: ['GET', 'HEAD'])]
    public function press(): Response
    {
        return $this->render('page/premsa.html.twig');
    }

    #[Route('/contacte', name: 'app_contact', methods: ['GET', 'HEAD'])]
    public function contact(): Response
    {
        return $this->render('page/contact.html.twig');
    }

    #[Route('/manual', name: 'app_manual', methods: ['GET', 'HEAD'])]
    #[Route('/ajuda', name: 'app_help', methods: ['GET', 'HEAD'])]
    public function manual(\App\Repository\PlanRepository $planRepository): Response
    {
        return $this->render('page/manual.html.twig', [
            'plans' => $this->getPlansByCode($planRepository),
        ]);
    }

    /**
     * @return array<string, \App\Entity\Plan>
     */
    private function getPlansByCode(\App\Repository\PlanRepository $planRepository): array
    {
        $plans = [];
        foreach ($planRepository->findAll() as $plan) {
            $plans[$plan->getCode()] = $plan;
        }

        return $plans;
    }

    /**
     * @return array<int, array{id: int, question: string, answer: string}>
     */
    private function loadFaqs(string $locale = 'ca'): array
    {
        $projectDir = dirname(__DIR__, 2);
        $path = $projectDir . '/content/faq/faqs.' . $locale . '.json';
        if (!is_file($path)) {
            $path = $projectDir . '/content/faq/faqs.ca.json';
        }
        if (!is_file($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
