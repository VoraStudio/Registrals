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

    #[Route('/blog', name: 'app_blog', methods: ['GET', 'HEAD'])]
    public function blog(): Response
    {
        return $this->render('page/blog.html.twig');
    }

    #[Route('/contacte', name: 'app_contact', methods: ['GET', 'HEAD'])]
    public function contact(): Response
    {
        return $this->render('page/contact.html.twig');
    }
}
