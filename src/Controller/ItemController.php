<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Entity\User;
use App\Enum\BlockchainActionType;
use App\Enum\DocumentType;
use App\Enum\ItemStatus;
use App\Form\ItemType;
use App\Message\AnchorBlockchainMessage;
use App\Repository\CategoryRepository;
use App\Repository\ItemRepository;
use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Security\Voter\ItemVoter;
use App\Service\Item\CuiGeneratorService;
use App\Service\Item\ItemLifecycleService;
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
        \Symfony\Component\Messenger\MessageBusInterface $messageBus,
        UserActivityLogger $activityLogger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Comprovació de quotes del pla actiu (els administradors no tenen límit de pla)
        if ($user->getPlan() !== null && !$user->isAdmin()) {
            if ($user->isRegistrationPeriodExpired()) {
                $user->resetRegistrationPeriod();
                $entityManager->flush();
            }

            // 1. Límit de capacitat simultània (béns actius vius a la caixa forta)
            $maxAllowedActive = $user->getPlan()->getMaxActiveItems();
            $activeItemsCount = $entityManager->getRepository(Item::class)->countActiveByOwner($user);

            if ($maxAllowedActive !== -1 && $maxAllowedActive < 9999 && $activeItemsCount >= $maxAllowedActive) {
                $this->addFlash('error', sprintf(
                    'Has assolit la capacitat màxima de %d béns actius simultanis del teu pla %s. Per registrar un nou bé, has de donar de baixa un bé existent o passar al Pla Plus.',
                    $maxAllowedActive,
                    $user->getPlan()->getName()
                ));
                return $this->redirectToRoute('app_dashboard');
            }

            // 2. Límit de pressupost d'altes anuals (regla anti-carrusel)
            $maxAllowedYearly = $user->getPlan()->getMaxYearlyRegistrations();
            $periodRegisteredCount = $user->getPeriodItemsRegistered();

            if ($maxAllowedYearly !== -1 && $maxAllowedYearly < 9999 && $periodRegisteredCount >= $maxAllowedYearly) {
                $renewsAt = $user->getCurrentPeriodStart()->modify('+1 year')->format('d/m/Y');
                $this->addFlash('error', sprintf(
                    'Has esgotat el límit de %d altes anuals del teu pla %s per a aquest cicle (renovació el %s). Passa al Pla Plus per gaudir de més altes.',
                    $maxAllowedYearly,
                    $user->getPlan()->getName(),
                    $renewsAt
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

                // Increment del comptador de béns registrats (període anual i històric)
                $user->incrementPeriodItemsRegistered();
                $user->setLifetimeItemsRegistered($user->getLifetimeItemsRegistered() + 1);

                $entityManager->flush();

                // Registre a la base d'auditoria forense (registrals_audit)
                $activityLogger->log(
                    user: $user,
                    action: UserActivityAction::ITEM_CREATED,
                    description: sprintf('Alta i registre de certificació del bé %s (%s)', $item->getName(), $item->getCui()),
                    details: [
                        'cui' => $item->getCui(),
                        'name' => $item->getName(),
                        'category' => $item->getCategory()?->getName(),
                        'brand' => $item->getBrand(),
                        'estimated_value' => $item->getDeclaredValueEuros(),
                    ]
                );

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
    public function show(
        string $cui,
        ItemRepository $itemRepository,
        \App\Service\ItemFingerprintCalculator $fingerprintCalculator,
        \App\Service\Transfer\TransferExpirationService $transferExpirationService
    ): Response {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException('El bé sol·licitat no existeix.');
        }

        // Si el bé està en estat de traspàs, verifica si el traspàs ha vençut (>72h) i concilia'l
        if ($item->getStatus() === \App\Enum\ItemStatus::TRANSFER_PENDING) {
            $transferExpirationService->expireTransfers($item->getOwner());
            // Recarrega l'estat actualitzat del bé
            $item = $itemRepository->findOneByCui($cui) ?? $item;
        }

        if (!$this->isGranted(ItemVoter::VIEW, $item)) {
            $this->addFlash('warning', sprintf(
                'El bé %s no està disponible al vostre patrimoni actiu perquè ha estat transmès a un altre titular.',
                $item->getCui()
            ));

            return $this->redirectToRoute('app_transfers_index');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $plan = $currentUser instanceof User ? $currentUser->getPlan() : null;
        $maxPhotos = $plan?->getMaxPhotosPerItem() ?? 1;
        $maxEvidences = $plan?->getMaxEvidencesPerItem() ?? 1;

        $canAddPhoto = ($maxPhotos <= 0 || count($item->getPhotos()) < $maxPhotos)
            && $this->isGranted(ItemVoter::EDIT, $item);
        $canAddEvidence = ($maxEvidences <= 0 || count($item->getEvidenceDocuments()) < $maxEvidences)
            && $this->isGranted(ItemVoter::EDIT, $item);

        return $this->render('item/show.html.twig', [
            'item' => $item,
            'currentFingerprint' => $fingerprintCalculator->calculateForItem($item),
            'maxPhotos' => $maxPhotos,
            'maxEvidences' => $maxEvidences,
            'canAddPhoto' => $canAddPhoto,
            'canAddEvidence' => $canAddEvidence,
            'plan' => $plan,
        ]);
    }

    /**
     * Adjunta una nova fotografia de detall o prova documental a un bé actiu,
     * validant les quotes del pla de l'usuari (Pla Free: 1/1, Pla Plus: 5/5, Unlimited: ∞)
     * i recalculant el fingerprint SHA-256 de custòdia.
     */
    #[Route('/{cui}/documents/add', name: 'document_add', methods: ['POST'])]
    public function addDocument(
        string $cui,
        Request $request,
        ItemRepository $itemRepository,
        SecureDocumentStorageService $storageService,
        MediaIntegrityValidator $integrityValidator,
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException('El bé sol·licitat no existeix.');
        }

        $this->denyAccessUnlessGranted(ItemVoter::EDIT, $item);

        if ($item->getStatus() !== ItemStatus::ACTIVE) {
            $this->addFlash('error', 'No es poden adjuntar documents a un bé que no estigui en estat actiu (congelat o en traspàs).');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('item_add_document_' . $item->getId(), $token)) {
            $this->addFlash('error', 'Token de seguretat invàlid. Si us plau, torna-ho a provar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        /** @var User $user */
        $user = $this->getUser();
        $plan = $user->getPlan();

        $kind = (string) $request->request->get('kind', 'photo'); // 'photo' | 'evidence'
        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'No s\'ha rebut cap fitxer vàlid per custodiar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        if ($kind === 'photo') {
            $maxPhotos = $plan?->getMaxPhotosPerItem() ?? 1;
            $currentPhotos = count($item->getPhotos());
            if ($maxPhotos > 0 && $currentPhotos >= $maxPhotos) {
                $this->addFlash('error', sprintf(
                    'Has assolit el límit màxim de %d fotografies permeses pel teu pla actual (%s).',
                    $maxPhotos,
                    $plan?->getName() ?? 'Pla Free'
                ));
                return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
            }

            $validation = $integrityValidator->validateItemPhoto($file);
            if (!$validation->isValid) {
                $this->addFlash('error', $validation->rejectionReason ?? 'La fotografia no ha superat els controls d\'integritat forense.');
                return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
            }

            $documentType = DocumentType::PHOTO_DETAIL;
            $metadata = $validation->metadata;
        } else {
            $maxEvidences = $plan?->getMaxEvidencesPerItem() ?? 1;
            $currentEvidences = count($item->getEvidenceDocuments());
            if ($maxEvidences > 0 && $currentEvidences >= $maxEvidences) {
                $this->addFlash('error', sprintf(
                    'Has assolit el límit màxim de %d proves documentals permeses pel teu pla actual (%s).',
                    $maxEvidences,
                    $plan?->getName() ?? 'Pla Free'
                ));
                return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
            }

            $validation = $integrityValidator->validateProofDocument($file);
            if (!$validation->isValid) {
                $this->addFlash('error', $validation->rejectionReason ?? 'El document de prova no ha superat els controls d\'integritat.');
                return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
            }

            $rawDocType = (string) $request->request->get('evidence_type', 'invoice_receipt');
            $candidateType = DocumentType::tryFrom($rawDocType) ?? DocumentType::INVOICE_RECEIPT;
            $documentType = $candidateType->isEvidence() ? $candidateType : DocumentType::INVOICE_RECEIPT;
            $metadata = $validation->metadata;
        }

        $storedDoc = $storageService->storeUploadedDocument($item, $file, $documentType, $metadata);

        // Actualització de timestamp per canviar automàticament el fingerprint SHA-256
        $item->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $activityLogger->log(
            user: $user,
            action: UserActivityAction::ITEM_UPDATED,
            description: sprintf(
                "Afegit nou document (%s: %s) al bé %s",
                $documentType->label(),
                $storedDoc->getOriginalFilename(),
                $item->getCui()
            ),
            details: [
                'cui' => $item->getCui(),
                'document_uuid' => $storedDoc->getUuid()->toRfc4122(),
                'document_type' => $documentType->value,
                'sha256_hash' => $storedDoc->getSha256Hash(),
                'filename' => $storedDoc->getOriginalFilename(),
            ]
        );

        $this->addFlash('success', sprintf(
            "S'ha afegit i custodiat amb èxit '%s' (%s). El fingerprint del bé s'ha actualitzat.",
            $storedDoc->getOriginalFilename(),
            $documentType->label()
        ));

        return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
    }

    /**
     * Estableix una fotografia de detall com a fotografia principal del bé.
     * Degrada l'antiga fotografia principal a fotografia de detall i recalcula el fingerprint SHA-256.
     */
    #[Route('/{cui}/documents/{uuid}/make-primary', name: 'document_make_primary', methods: ['POST'])]
    public function makePrimary(
        string $cui,
        string $uuid,
        Request $request,
        ItemRepository $itemRepository,
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException('El bé sol·licitat no existeix.');
        }

        $this->denyAccessUnlessGranted(ItemVoter::EDIT, $item);

        if ($item->getStatus() !== ItemStatus::ACTIVE) {
            $this->addFlash('error', 'No es pot modificar un bé que no estigui en estat actiu.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('item_doc_action_' . $item->getId(), $token)) {
            $this->addFlash('error', 'Token de seguretat invàlid. Si us plau, torna-ho a provar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $targetDoc = null;
        try {
            $targetUuid = \Symfony\Component\Uid\Uuid::fromString($uuid);
            foreach ($item->getPhotos() as $photo) {
                if ($photo->getUuid()->equals($targetUuid)) {
                    $targetDoc = $photo;
                    break;
                }
            }
        } catch (\Throwable) {
            $targetDoc = null;
        }

        if (!$targetDoc) {
            $this->addFlash('error', 'No s\'ha trobat la fotografia sol·licitada.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        if ($targetDoc->getType() === DocumentType::PHOTO_PRIMARY) {
            $this->addFlash('info', 'Aquesta fotografia ja és la fotografia principal de referència.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        // Degradar l'actual fotografia principal a foto de detall
        foreach ($item->getPhotos() as $photo) {
            if ($photo->getType() === DocumentType::PHOTO_PRIMARY) {
                $photo->setType(DocumentType::PHOTO_DETAIL);
            }
        }

        // Promocionar la foto seleccionada a principal
        $targetDoc->setType(DocumentType::PHOTO_PRIMARY);
        $item->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        /** @var User $user */
        $user = $this->getUser();
        $activityLogger->log(
            user: $user,
            action: UserActivityAction::ITEM_UPDATED,
            description: sprintf("Establerta nova fotografia principal (%s) al bé %s", $targetDoc->getOriginalFilename(), $item->getCui()),
            details: [
                'cui' => $item->getCui(),
                'document_uuid' => $targetDoc->getUuid()->toRfc4122(),
                'sha256_hash' => $targetDoc->getSha256Hash(),
            ]
        );

        $this->addFlash('success', "Fotografia principal actualitzada amb èxit! L'empremta SHA-256 del bé s'ha recalculat. Si tenies un certificat oficial v1 emès, ha quedat superat per la versió v2.");

        return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
    }

    /**
     * Elimina un document o fotografia secundària del bé custodiat.
     * Invariant: un bé actiu mai pot quedar sense fotografia (mínim 1 foto).
     */
    #[Route('/{cui}/documents/{uuid}/delete', name: 'document_delete', methods: ['POST'])]
    public function deleteDocument(
        string $cui,
        string $uuid,
        Request $request,
        ItemRepository $itemRepository,
        SecureDocumentStorageService $storageService,
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException('El bé sol·licitat no existeix.');
        }

        $this->denyAccessUnlessGranted(ItemVoter::EDIT, $item);

        if ($item->getStatus() !== ItemStatus::ACTIVE) {
            $this->addFlash('error', 'No es pot modificar un bé que no estigui en estat actiu.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('item_doc_action_' . $item->getId(), $token)) {
            $this->addFlash('error', 'Token de seguretat invàlid. Si us plau, torna-ho a provar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $targetDoc = null;
        try {
            $targetUuid = \Symfony\Component\Uid\Uuid::fromString($uuid);
            foreach ($item->getDocuments() as $doc) {
                if ($doc->getUuid()->equals($targetUuid)) {
                    $targetDoc = $doc;
                    break;
                }
            }
        } catch (\Throwable) {
            $targetDoc = null;
        }

        if (!$targetDoc) {
            $this->addFlash('error', 'No s\'ha trobat el document que es vol eliminar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        // Invariant: Un bé mai pot quedar sense fotos
        if ($targetDoc->getType()->isPhoto()) {
            if (count($item->getPhotos()) <= 1) {
                $this->addFlash('error', 'Un bé custodiat ha de tenir com a mínim 1 fotografia de referència. Si vols canviar-la, pots utilitzar l\'opció de substituir.');
                return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
            }

            // Si s'elimina la foto principal però en queden d'altres, promocionem la primera restant a principal
            if ($targetDoc->getType() === DocumentType::PHOTO_PRIMARY) {
                foreach ($item->getPhotos() as $otherPhoto) {
                    if (!$otherPhoto->getUuid()->equals($targetDoc->getUuid())) {
                        $otherPhoto->setType(DocumentType::PHOTO_PRIMARY);
                        break;
                    }
                }
            }
        }

        $docName = $targetDoc->getOriginalFilename();
        $docTypeLabel = $targetDoc->getType()->label();
        $docHash = $targetDoc->getSha256Hash();

        $storageService->deleteDocumentFile($targetDoc);
        $item->removeDocument($targetDoc);
        $entityManager->remove($targetDoc);

        $item->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        /** @var User $user */
        $user = $this->getUser();
        $activityLogger->log(
            user: $user,
            action: UserActivityAction::ITEM_UPDATED,
            description: sprintf("Eliminat document custodiat (%s: %s) del bé %s", $docTypeLabel, $docName, $item->getCui()),
            details: [
                'cui' => $item->getCui(),
                'filename' => $docName,
                'sha256_hash' => $docHash,
            ]
        );

        $this->addFlash('success', sprintf("S'ha eliminat el document '%s'. L'empremta SHA-256 del bé s'ha recalculat.", $docName));

        return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
    }

    /**
     * Substitueix un document o fotografia existent per un fitxer nou en una sola operació atòmica.
     */
    #[Route('/{cui}/documents/{uuid}/replace', name: 'document_replace', methods: ['POST'])]
    public function replaceDocument(
        string $cui,
        string $uuid,
        Request $request,
        ItemRepository $itemRepository,
        SecureDocumentStorageService $storageService,
        MediaIntegrityValidator $integrityValidator,
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException('El bé sol·licitat no existeix.');
        }

        $this->denyAccessUnlessGranted(ItemVoter::EDIT, $item);

        if ($item->getStatus() !== ItemStatus::ACTIVE) {
            $this->addFlash('error', 'No es pot modificar un bé que no estigui en estat actiu.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('item_doc_action_' . $item->getId(), $token)) {
            $this->addFlash('error', 'Token de seguretat invàlid. Si us plau, torna-ho a provar.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $targetDoc = null;
        try {
            $targetUuid = \Symfony\Component\Uid\Uuid::fromString($uuid);
            foreach ($item->getDocuments() as $doc) {
                if ($doc->getUuid()->equals($targetUuid)) {
                    $targetDoc = $doc;
                    break;
                }
            }
        } catch (\Throwable) {
            $targetDoc = null;
        }

        if (!$targetDoc) {
            $this->addFlash('error', 'No s\'ha trobat el document que es vol substituir.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'No s\'ha rebut cap fitxer vàlid per a la substitució.');
            return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
        }

        $isPhoto = $targetDoc->getType()->isPhoto();
        $targetType = $targetDoc->getType();

        if ($isPhoto) {
            $validation = $integrityValidator->validateItemPhoto($file);
            if (!$validation->isValid) {
                $this->addFlash('error', $validation->rejectionReason ?? 'La nova fotografia no ha superat els controls d\'integritat forense.');
                return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
            }
            $metadata = $validation->metadata;
        } else {
            $validation = $integrityValidator->validateProofDocument($file);
            if (!$validation->isValid) {
                $this->addFlash('error', $validation->rejectionReason ?? 'El nou document de prova no ha superat els controls d\'integritat.');
                return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
            }
            $rawDocType = (string) $request->request->get('evidence_type');
            if ($rawDocType !== '') {
                $candidateType = DocumentType::tryFrom($rawDocType);
                if ($candidateType && $candidateType->isEvidence()) {
                    $targetType = $candidateType;
                }
            }
            $metadata = $validation->metadata;
        }

        $oldName = $targetDoc->getOriginalFilename();

        // Elimina el fitxer físic anterior
        $storageService->deleteDocumentFile($targetDoc);
        $item->removeDocument($targetDoc);
        $entityManager->remove($targetDoc);

        // Emmagatzema el nou fitxer mantenint el tipus (per exemple, PHOTO_PRIMARY)
        $newDoc = $storageService->storeUploadedDocument($item, $file, $targetType, $metadata);

        $item->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        /** @var User $user */
        $user = $this->getUser();
        $activityLogger->log(
            user: $user,
            action: UserActivityAction::ITEM_UPDATED,
            description: sprintf("Substituït document custodiat (%s per %s) al bé %s", $oldName, $newDoc->getOriginalFilename(), $item->getCui()),
            details: [
                'cui' => $item->getCui(),
                'old_filename' => $oldName,
                'new_filename' => $newDoc->getOriginalFilename(),
                'new_sha256' => $newDoc->getSha256Hash(),
            ]
        );

        $this->addFlash('success', sprintf("S'ha substituït el document '%s' per '%s'. L'empremta SHA-256 del bé s'ha actualitzat.", $oldName, $newDoc->getOriginalFilename()));

        return $this->redirectToRoute('app_item_show', ['cui' => $cui]);
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
        $isUnlimited = ($planQuota === -1 || $planQuota >= 9999);
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

    /**
     * Dóna de baixa (arxiva) o elimina permanentment un bé individual,
     * protegint la integritat jurídica si disposa de certificats oficials o traspassos.
     */
    #[Route('/{cui}/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(
        string $cui,
        Request $request,
        ItemRepository $itemRepository,
        SecureDocumentStorageService $storageService,
        ItemLifecycleService $lifecycleService,
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        $item = $itemRepository->findOneByCui($cui);

        if (!$item) {
            throw $this->createNotFoundException('El bé sol·licitat no existeix.');
        }

        $this->denyAccessUnlessGranted(ItemVoter::DELETE, $item);

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('deactivate_item_' . $item->getId(), $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');
            return $this->redirectToRoute('app_dashboard');
        }

        $actionType = (string) $request->request->get('action_type', 'archive');

        // Comprovació d'immutabilitat jurídica:
        // Si té certificats oficials emesos o traspassos registrats, no es pot purgar mai
        $hasOfficialCertificates = false;
        foreach ($item->getCertificates() as $cert) {
            if ($cert->getMode()->isOfficial()) {
                $hasOfficialCertificates = true;
                break;
            }
        }
        $hasTransfers = !$item->getTransfers()->isEmpty();
        $isLegallyProtected = $hasOfficialCertificates || $hasTransfers;

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if ($actionType === 'delete' && $isLegallyProtected) {
            $this->addFlash('warning', sprintf(
                'El bé %s disposa de certificats oficials o d\'un històric de traspassos. Per integritat jurídica davant tercers, no es pot purgar completament, però s\'ha donat de baixa i arxivat.',
                $item->getCui()
            ));
            $actionType = 'archive';
        }

        if ($actionType === 'delete' && !$isLegallyProtected) {
            // Hard Delete: purga de fitxers i registre
            foreach ($item->getPhotos() as $photo) {
                $storageService->deleteDocumentFile($photo);
            }
            foreach ($item->getEvidenceDocuments() as $doc) {
                $storageService->deleteDocumentFile($doc);
            }

            $activityLogger->log(
                user: $currentUser,
                action: UserActivityAction::ITEM_DELETED,
                description: sprintf('Eliminació permanent del bé %s (%s)', $item->getName(), $item->getCui()),
                details: ['cui' => $item->getCui(), 'name' => $item->getName()]
            );

            $entityManager->remove($item);
            $entityManager->flush();

            $this->addFlash('info', sprintf('El bé %s s\'ha suprimit permanentment del vostre patrimoni.', $item->getName()));
        } else {
            // Soft Delete / Baixa: Arxiva a custòdia en fred
            if ($item->getStatus() === ItemStatus::ACTIVE) {
                $lifecycleService->archive($item);
            } else {
                $item->setStatus(ItemStatus::ARCHIVED);
                $entityManager->flush();
            }

            $activityLogger->log(
                user: $currentUser,
                action: UserActivityAction::STATUS_CHANGED,
                description: sprintf('Donat de baixa i arxivat el bé %s (%s)', $item->getName(), $item->getCui()),
                details: ['cui' => $item->getCui(), 'new_status' => ItemStatus::ARCHIVED->value]
            );

            $this->addFlash('info', sprintf('El bé %s s\'ha donat de baixa del patrimoni actiu i s\'ha arxivat.', $item->getName()));
        }

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * Dóna de baixa un lot de béns seleccionats al Dashboard.
     */
    #[Route('/batch/deactivate', name: 'batch_deactivate', methods: ['POST'])]
    public function batchDeactivate(
        Request $request,
        ItemRepository $itemRepository,
        SecureDocumentStorageService $storageService,
        ItemLifecycleService $lifecycleService,
        EntityManagerInterface $entityManager,
        UserActivityLogger $activityLogger
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('batch_deactivate_items', $token)) {
            $this->addFlash('error', 'El testimoni de seguretat no és vàlid.');
            return $this->redirectToRoute('app_dashboard');
        }

        $cuis = (array) $request->request->all('cuis');
        if (empty($cuis)) {
            $this->addFlash('warning', 'No s\'ha seleccionat cap bé per donar de baixa.');
            return $this->redirectToRoute('app_dashboard');
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $archivedCount = 0;
        $deletedCount = 0;
        $actionType = (string) $request->request->get('action_type', 'archive');

        foreach ($cuis as $cui) {
            $item = $itemRepository->findOneByCui((string) $cui);
            if (!$item || !$this->isGranted(ItemVoter::DELETE, $item)) {
                continue;
            }

            $hasOfficial = false;
            foreach ($item->getCertificates() as $cert) {
                if ($cert->getMode()->isOfficial()) {
                    $hasOfficial = true;
                    break;
                }
            }
            $isLegallyProtected = $hasOfficial || !$item->getTransfers()->isEmpty();

            if ($actionType === 'delete' && !$isLegallyProtected) {
                foreach ($item->getPhotos() as $photo) {
                    $storageService->deleteDocumentFile($photo);
                }
                foreach ($item->getEvidenceDocuments() as $doc) {
                    $storageService->deleteDocumentFile($doc);
                }
                $entityManager->remove($item);
                ++$deletedCount;
            } else {
                if ($item->getStatus() === ItemStatus::ACTIVE) {
                    $lifecycleService->archive($item);
                } else {
                    $item->setStatus(ItemStatus::ARCHIVED);
                }
                ++$archivedCount;
            }
        }

        $entityManager->flush();

        $messages = [];
        if ($archivedCount > 0) {
            $messages[] = sprintf('%d bé(ns) donat(s) de baixa i arxivat(s)', $archivedCount);
        }
        if ($deletedCount > 0) {
            $messages[] = sprintf('%d bé(ns) suprimit(s) definitivament', $deletedCount);
        }

        if (!empty($messages)) {
            $this->addFlash('info', 'Operació completada: ' . implode(', ', $messages) . '.');
        }

        return $this->redirectToRoute('app_dashboard');
    }
}

