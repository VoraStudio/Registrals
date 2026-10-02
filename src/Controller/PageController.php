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
    public function home(): Response
    {
        return $this->render('page/home.html.twig');
    }

    #[Route('/legal', name: 'app_legal', methods: ['GET', 'HEAD'])]
    public function legal(): Response
    {
        return $this->render('page/legal.html.twig');
    }
}
