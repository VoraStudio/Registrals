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
    public function home(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        // 1. Si ja té sessió activa, entra directament al dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // 2. Si el dispositiu està reconegut, salta la landing i va al desbloqueig ràpid
        if ($request->cookies->has(\App\EventSubscriber\DeviceRecognitionSubscriber::COOKIE_NAME)) {
            return $this->redirectToRoute('app_login');
        }

        // 3. Visitant nou o sessió desconeguda: mostra la Landing Page corporativa
        return $this->render('page/home.html.twig');
    }

    #[Route('/legal', name: 'app_legal', methods: ['GET', 'HEAD'])]
    public function legal(): Response
    {
        return $this->render('page/legal.html.twig');
    }
}
