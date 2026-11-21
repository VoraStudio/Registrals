<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FaqItem;
use App\Repository\FaqItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/landing/faqs')]
#[IsGranted('ROLE_ADMIN')]
class AdminFaqController extends AbstractController
{
    #[Route('', name: 'app_admin_faqs', methods: ['GET'])]
    public function index(FaqItemRepository $repository): Response
    {
        return $this->render('admin/faq/index.html.twig', [
            'faqs' => $repository->findAllOrdered(),
        ]);
    }

    #[Route('/nova', name: 'app_admin_faqs_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('admin_faq_create', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_faqs');
        }

        $question = trim((string) $request->request->get('question'));
        $answer = trim((string) $request->request->get('answer'));
        $sortOrder = (int) $request->request->get('sort_order', 0);
        $isActive = $request->request->getBoolean('is_active', true);

        if ($question === '' || $answer === '') {
            $this->addFlash('error', 'La pregunta i la resposta són obligatòries.');
            return $this->redirectToRoute('app_admin_faqs');
        }

        $faq = new FaqItem(
            question: $question,
            answer: $answer,
            isActive: $isActive,
            sortOrder: $sortOrder
        );

        $entityManager->persist($faq);
        $entityManager->flush();

        $this->addFlash('success', 'Pregunta freqüent creada.');

        return $this->redirectToRoute('app_admin_faqs');
    }

    #[Route('/{id}/editar', name: 'app_admin_faqs_edit', methods: ['POST'])]
    public function edit(
        int $id,
        Request $request,
        FaqItemRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_faq_edit_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_faqs');
        }

        $faq = $repository->find($id);
        if (!$faq) {
            throw $this->createNotFoundException('FAQ no trobada.');
        }

        $question = trim((string) $request->request->get('question'));
        $answer = trim((string) $request->request->get('answer'));
        if ($question === '' || $answer === '') {
            $this->addFlash('error', 'La pregunta i la resposta són obligatòries.');
            return $this->redirectToRoute('app_admin_faqs');
        }

        $faq->setQuestion($question);
        $faq->setAnswer($answer);
        $faq->setSortOrder((int) $request->request->get('sort_order', 0));
        $faq->setIsActive($request->request->getBoolean('is_active', false));

        $entityManager->flush();

        $this->addFlash('success', 'Pregunta freqüent actualitzada.');

        return $this->redirectToRoute('app_admin_faqs');
    }

    #[Route('/{id}/toggle', name: 'app_admin_faqs_toggle', methods: ['POST'])]
    public function toggle(
        int $id,
        Request $request,
        FaqItemRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_faq_toggle_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_faqs');
        }

        $faq = $repository->find($id);
        if ($faq) {
            $faq->setIsActive(!$faq->isActive());
            $entityManager->flush();
            $this->addFlash('success', $faq->isActive() ? 'Pregunta activada.' : 'Pregunta desactivada.');
        }

        return $this->redirectToRoute('app_admin_faqs');
    }

    #[Route('/{id}/eliminar', name: 'app_admin_faqs_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        FaqItemRepository $repository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('admin_faq_delete_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de seguretat invàlid.');
            return $this->redirectToRoute('app_admin_faqs');
        }

        $faq = $repository->find($id);
        if ($faq) {
            $entityManager->remove($faq);
            $entityManager->flush();
            $this->addFlash('success', 'Pregunta eliminada.');
        }

        return $this->redirectToRoute('app_admin_faqs');
    }
}
