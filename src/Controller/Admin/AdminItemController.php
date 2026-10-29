<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Item;
use App\Enum\ItemStatus;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/bens')]
#[IsGranted('ROLE_ADMIN')]
class AdminItemController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    /**
     * Llistat global i cercador de béns custodiats a la plataforma
     */
    #[Route('', name: 'app_admin_items', methods: ['GET'])]
    public function index(Request $request, ItemRepository $itemRepository): Response
    {
        $search = $request->query->get('q');
        $status = $request->query->get('status');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);
        if (!in_array($limit, [10, 25, 50], true)) {
            $limit = 10;
        }

        $items = $itemRepository->findPaginatedForAdmin($search, $status, $page, $limit);
        $totalItems = $itemRepository->countForAdmin($search, $status);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        return $this->render('admin/item/index.html.twig', [
            'items' => $items,
            'totalItems' => $totalItems,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'currentSearch' => $search,
            'currentStatus' => $status,
            'currentLimit' => $limit,
            'statuses' => ItemStatus::cases(),
        ]);
    }

    /**
     * Fitxa d'Inspecció Notarial i Auditoria d'un Bé Custodiat (Backoffice)
     */
    #[Route('/{cui}', name: 'app_admin_item_show', methods: ['GET'])]
    public function show(string $cui, ItemRepository $itemRepository): Response
    {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException(sprintf('El bé amb codi notarial %s no s\'ha trobat al registre.', $cui));
        }

        return $this->render('admin/item/show.html.twig', [
            'item' => $item,
            'statuses' => ItemStatus::cases(),
        ]);
    }

    /**
     * Canvi d'estat notarial d'un bé des del Backoffice (Auditoria / Bloqueig per frau)
     */
    #[Route('/{cui}/estat', name: 'app_admin_item_update_status', methods: ['POST'])]
    public function updateStatus(
        string $cui,
        Request $request,
        ItemRepository $itemRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException(sprintf('El bé %s no s\'ha trobat.', $cui));
        }

        $csrfToken = (string) $request->request->get('_csrf_token');
        if (!$this->isCsrfTokenValid('admin_item_status_' . $item->getId(), $csrfToken)) {
            $this->addFlash('error', 'Token de seguretat CSRF invàlid.');
            return $this->redirectToRoute('app_admin_item_show', ['cui' => $cui]);
        }

        $newStatusValue = (string) $request->request->get('status');
        $newStatus = ItemStatus::tryFrom($newStatusValue);

        if (!$newStatus) {
            $this->addFlash('error', 'Estat no vàlid sol·licitat.');
            return $this->redirectToRoute('app_admin_item_show', ['cui' => $cui]);
        }

        $previousStatus = $item->getStatus();
        $item->setStatus($newStatus);
        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'L\'estat del bé %s s\'ha actualitzat de "%s" a "%s" correctament.',
            $item->getCui(),
            $previousStatus->label(),
            $newStatus->label()
        ));

        return $this->redirectToRoute('app_admin_item_show', ['cui' => $cui]);
    }
}
