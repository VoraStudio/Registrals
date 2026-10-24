<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Form\ItemType;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\Voter\ItemVoter;
use App\Service\Item\CuiGeneratorService;
use App\Service\Storage\SecureDocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/app/items', name: 'app_item_')]
#[IsGranted('ROLE_USER')]
class ItemController extends AbstractController
{
    /**
     * Wizard d'Alta de Bé Moble (Passos: 1. Dades -> 2. Proves/Fotos -> 3. Confirmació i CUI)
     */
    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CategoryRepository $categoryRepository,
        CuiGeneratorService $cuiGenerator,
        SecureDocumentStorageService $storageService,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Comprovació de quota del pla actiu
        $activeItemsCount = $user->getLifetimeItemsRegistered();
        $maxAllowed = $user->getPlan()->getMaxActiveItems();

        if ($activeItemsCount >= $maxAllowed) {
            $this->addFlash('error', sprintf(
                'Has assolit el límit màxim de %d béns del teu pla %s. Passa a un pla superior per continuar protegint el teu patrimoni.',
                $maxAllowed,
                $user->getPlan()->getName()
            ));
            return $this->redirectToRoute('app_dashboard');
        }

        $defaultCategory = $categoryRepository->findOneBy(['slug' => 'altres-bens'])
            ?? $categoryRepository->findAll()[0];

        $cui = $cuiGenerator->generate();
        $item = new Item($user, $cui, '', $defaultCategory);

        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($item);

            // Gestió de la fotografia principal
            /** @var UploadedFile|null $primaryPhoto */
            $primaryPhoto = $form->get('primaryPhoto')->getData();
            if ($primaryPhoto instanceof UploadedFile) {
                $storageService->storeUploadedDocument($item, $primaryPhoto, DocumentType::PHOTO_PRIMARY);
            }

            // Gestió del comprovant/factura d'adquisició
            /** @var UploadedFile|null $invoiceFile */
            $invoiceFile = $form->get('invoiceFile')->getData();
            if ($invoiceFile instanceof UploadedFile) {
                $storageService->storeUploadedDocument($item, $invoiceFile, DocumentType::INVOICE_RECEIPT);
            }

            // Increment del comptador de béns registrats
            $user->setLifetimeItemsRegistered($user->getLifetimeItemsRegistered() + 1);

            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Bé registrat amb èxit! S\'ha generat el codi notarial únic %s.',
                $item->getCui()
            ));

            return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
        }

        $rootCategories = $categoryRepository->findRootCategories();

        return $this->render('item/new.html.twig', [
            'item' => $item,
            'form' => $form,
            'cui' => $cui,
            'categories' => $rootCategories,
        ]);
    }

    /**
     * Fitxa de Detall del Bé Custodiat
     */
    #[Route('/{cui}', name: 'show', methods: ['GET'])]
    public function show(string $cui, ItemRepository $itemRepository): Response
    {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException('El bé sol·licitat no existeix.');
        }

        $this->denyAccessUnlessGranted(ItemVoter::VIEW, $item);

        return $this->render('item/show.html.twig', [
            'item' => $item,
        ]);
    }
}
