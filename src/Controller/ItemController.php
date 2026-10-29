<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Enum\BlockchainActionType;
use App\Enum\DocumentType;
use App\Form\ItemType;
use App\Message\AnchorBlockchainMessage;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Security\Voter\ItemVoter;
use App\Service\Item\CuiGeneratorService;
use App\Service\Storage\SecureDocumentStorageService;
use App\Service\Validation\MediaIntegrityValidator;
use Doctrine\ORM\EntityManagerInterface;
use App\Infrastructure\Adapter\Ai\AiVisionAnalyzerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        MediaIntegrityValidator $integrityValidator,
        EntityManagerInterface $entityManager,
        \Symfony\Component\Messenger\MessageBusInterface $messageBus
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Comprovació de quota del pla actiu (els administradors no tenen límit de pla)
        if ($user->getPlan() !== null) {
            $activeItemsCount = $user->getLifetimeItemsRegistered();
            $maxAllowed = $user->getPlan()->getMaxActiveItems();

            if ($maxAllowed !== -1 && $activeItemsCount >= $maxAllowed) {
                $this->addFlash('error', sprintf(
                    'Has assolit el límit màxim de %d béns del teu pla %s. Passa a un pla superior per continuar protegint el teu patrimoni.',
                    $maxAllowed,
                    $user->getPlan()->getName()
                ));
                return $this->redirectToRoute('app_dashboard');
            }
        }

        $defaultCategory = $categoryRepository->findOneBy(['slug' => 'altres-bens'])
            ?? $categoryRepository->findAll()[0];

        $cui = $cuiGenerator->generate();
        $item = new Item($user, $cui, '', $defaultCategory);

        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hasIntegrityErrors = false;

            // Validació d'integritat de la fotografia principal
            /** @var UploadedFile|null $primaryPhoto */
            $primaryPhoto = $form->get('primaryPhoto')->getData();
            $photoMetadata = null;
            if ($primaryPhoto instanceof UploadedFile) {
                $photoCheck = $integrityValidator->validateItemPhoto($primaryPhoto);
                if (!$photoCheck->isValid) {
                    $form->get('primaryPhoto')->addError(new FormError($photoCheck->rejectionReason ?? 'Fotografia no vàlida.'));
                    $hasIntegrityErrors = true;
                } else {
                    $photoMetadata = $photoCheck->metadata;
                }
            }

            // Validació d'integritat del comprovant/factura d'adquisició
            /** @var UploadedFile|null $invoiceFile */
            $invoiceFile = $form->get('invoiceFile')->getData();
            $invoiceMetadata = null;
            if ($invoiceFile instanceof UploadedFile) {
                $invoiceCheck = $integrityValidator->validateProofDocument($invoiceFile);
                if (!$invoiceCheck->isValid) {
                    $form->get('invoiceFile')->addError(new FormError($invoiceCheck->rejectionReason ?? 'Document de prova no vàlid.'));
                    $hasIntegrityErrors = true;
                } else {
                    $invoiceMetadata = $invoiceCheck->metadata;
                }
            }

            if (!$hasIntegrityErrors) {
                $entityManager->persist($item);

                if ($primaryPhoto instanceof UploadedFile) {
                    $storageService->storeUploadedDocument($item, $primaryPhoto, DocumentType::PHOTO_PRIMARY, $photoMetadata);
                }

                if ($invoiceFile instanceof UploadedFile) {
                    $storageService->storeUploadedDocument($item, $invoiceFile, DocumentType::INVOICE_RECEIPT, $invoiceMetadata);
                }

                // Increment del comptador de béns registrats
                $user->setLifetimeItemsRegistered($user->getLifetimeItemsRegistered() + 1);

                $entityManager->flush();

                // Notarització asíncrona a Polygon Blockchain (Layer 2)
                $messageBus->dispatch(new AnchorBlockchainMessage($item->getId(), BlockchainActionType::ITEM_REGISTRATION));

                $this->addFlash('success', sprintf(
                    'Bé registrat amb èxit! S\'ha generat el codi notarial únic %s i s\'ha enviat a notaritzar a Polygon Blockchain.',
                    $item->getCui()
                ));

                return $this->redirectToRoute('app_item_show', ['cui' => $item->getCui()]);
            }
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

    /**
     * Endpoint AJAX per a l'anàlisi de visió per computador i OCR en temps real (SGLang).
     */
    #[Route('/analyze-media', name: 'analyze_media', methods: ['POST'])]
    public function analyzeMedia(
        Request $request,
        AiVisionAnalyzerInterface $aiAnalyzer,
        CategoryRepository $categoryRepository,
        MediaIntegrityValidator $integrityValidator,
        EntityManagerInterface $entityManager,
        \App\Repository\SystemSettingRepository $settingRepository,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['success' => false, 'error' => 'No s\'ha rebut cap fitxer.'], Response::HTTP_BAD_REQUEST);
        }

        $promptType = $request->request->get('promptType', 'photo');
        if (!in_array($promptType, ['photo', 'invoice'], true)) {
            $promptType = 'photo';
        }

        $realPath = $file->getRealPath();
        if ($realPath === false || !file_exists($realPath)) {
            return $this->json(['success' => false, 'error' => 'Fitxer temporal inaccessible.'], Response::HTTP_BAD_REQUEST);
        }

        // 1. GUÀRDIA D'INTEGRITAT I ANTI-FRAU FORENSE (C2PA / Generadors d'IA)
        // Aquest filtre s'executa SEMPRE en 0.5 ms, tant si la IA està activada com si està desactivada.
        $integrity = $promptType === 'photo'
            ? $integrityValidator->validateItemPhoto($file)
            : $integrityValidator->validateProofDocument($file);

        if (!$integrity->isValid) {
            return $this->json([
                'success' => false,
                'code' => 'AI_SYNTHETIC_REJECTED',
                'error' => $integrity->rejectionReason,
                'detectedGenerator' => $integrity->detectedGenerator,
                'integrityVerified' => false,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 2. Guard de Feature Toggle Global en calent des de Backoffice (SystemSetting)
        $aiGlobalEnabled = $settingRepository->getBool('ai_global_enabled', (bool) $this->getParameter('app.ai_assistance_enabled'));
        if (!$aiGlobalEnabled) {
            return $this->json([
                'success' => true,
                'aiEnabled' => false,
                'integrityVerified' => true,
                'message' => 'L\'arxiu ha superat les validacions d\'autenticitat. L\'assistència d\'IA està temporalment pausada des de l\'administració per protecció del node local SGLang; pots omplir les dades manualment.',
                'data' => [
                    'exif' => $integrity->metadata['exif'] ?? [],
                    'warnings' => $integrity->warnings,
                ],
            ]);
        }

        // 3. Guard d'Assistència d'IA per Pla de l'usuari
        $plan = $user->getPlan();
        if ($plan !== null && !$plan->isAiAssistanceEnabled()) {
            return $this->json([
                'success' => true,
                'aiEnabled' => false,
                'integrityVerified' => true,
                'message' => sprintf('L\'arxiu és autèntic. L\'assistència d\'IA no està habilitada per al pla "%s"; pots omplir les dades manualment.', $plan->getName()),
                'data' => [
                    'exif' => $integrity->metadata['exif'] ?? [],
                    'warnings' => $integrity->warnings,
                ],
            ]);
        }

        // 4. Guard de Quota de Crèdits IA segons el Pla
        $planQuota = $plan ? $plan->getAiAssistanceQuota() : -1;
        $usage = $user->getAiUsageCount();
        $isUnlimited = ($planQuota === -1);
        $remainingCredits = $isUnlimited ? 999999 : max(0, $planQuota - $usage);

        if (!$isUnlimited && $remainingCredits <= 0) {
            return $this->json([
                'success' => false,
                'code' => 'AI_QUOTA_EXCEEDED',
                'integrityVerified' => true,
                'error' => sprintf(
                    'Has exhaurit els teus crèdits d\'IA (%d/%d consultes utilitzades). L\'arxiu és vàlid, pots continuar omplint les dades manualment.',
                    $usage,
                    $planQuota
                ),
                'quota' => $planQuota,
                'usage' => $usage,
                'creditsRemaining' => 0,
            ], Response::HTTP_FORBIDDEN);
        }

        $fileContent = file_get_contents($realPath);
        if ($fileContent === false) {
            return $this->json(['success' => false, 'error' => 'Error llegint el fitxer.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $mimeType = $file->getMimeType() ?? 'image/jpeg';

        $extraction = $aiAnalyzer->analyzeMedia($fileContent, $mimeType, $promptType);
        $isSuccessful = !($extraction->confidence <= 0.0 && str_contains($extraction->notes ?? '', 'no disponible'));

        // 4. Incrementem el comptador de consultes d'IA consumides només si el servei ha respost amb èxit
        if ($isSuccessful) {
            $user->incrementAiUsage();
            $entityManager->flush();
        }

        $remainingAfter = $isUnlimited ? -1 : max(0, $planQuota - $user->getAiUsageCount());

        $categoryId = null;
        if ($extraction->categorySlug) {
            $category = $categoryRepository->findOneBy(['slug' => $extraction->categorySlug]);
            $categoryId = $category?->getId();
        }

        $subcategoryId = null;
        if ($extraction->subcategorySlug) {
            $subcategory = $categoryRepository->findOneBy(['slug' => $extraction->subcategorySlug]);
            $subcategoryId = $subcategory?->getId();
            if ($subcategory && $subcategory->getParent() && !$categoryId) {
                $categoryId = $subcategory->getParent()->getId();
            }
        }

        // 5. Validacions deterministes en PHP (Dígit de control NIF/CIF i Aritmètica del document)
        $taxIdValid = null;
        if ($extraction->taxId) {
            $taxIdValid = $integrityValidator->validateSpanishTaxId($extraction->taxId);
        }

        $arithmeticCheck = null;
        if ($extraction->declaredValueEuros !== null && $extraction->subtotalEuros !== null) {
            $arithmeticCheck = $integrityValidator->validateInvoiceArithmetic(
                $extraction->subtotalEuros,
                $extraction->taxAmountEuros,
                $extraction->declaredValueEuros
            );
        }

        $responseData = array_merge($extraction->toArray(), [
            'categoryId' => $categoryId,
            'subcategoryId' => $subcategoryId,
            'taxIdValid' => $taxIdValid,
            'arithmeticCheck' => $arithmeticCheck,
            'integrityVerified' => true,
            'warnings' => $integrity->warnings,
        ]);

        return $this->json([
            'success' => true,
            'creditsRemaining' => $remainingAfter,
            'data' => $responseData,
        ]);
    }
}

