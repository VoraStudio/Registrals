<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ItemDocument;
use App\Enum\DocumentType;
use App\Infrastructure\Adapter\Ai\AiVisionAnalyzerInterface;
use App\Message\AnalyzeItemMediaMessage;
use App\Repository\ItemRepository;
use App\Service\Storage\SecureDocumentStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Processador asíncron de Symfony Messenger per a l'anàlisi de visió i OCR mitjançant SGLang.
 */
#[AsMessageHandler]
final readonly class AnalyzeItemMediaMessageHandler
{
    public function __construct(
        private ItemRepository $itemRepository,
        private SecureDocumentStorageService $storageService,
        private AiVisionAnalyzerInterface $aiAnalyzer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AnalyzeItemMediaMessage $message): void
    {
        $this->logger->info('Iniciant anàlisi asíncrona de mitjà d\'ítem', [
            'item_id' => $message->itemId,
            'media_uuid' => $message->mediaUuid,
            'media_type' => $message->mediaType,
        ]);

        $item = $this->itemRepository->find($message->itemId);
        if (!$item) {
            $this->logger->warning('Ítem no trobat per a l\'anàlisi d\'IA', ['item_id' => $message->itemId]);
            return;
        }

        // Trobar el document pel seu UUID
        $targetDoc = null;
        try {
            $targetUuid = Uuid::fromString($message->mediaUuid);
            foreach ($item->getDocuments() as $doc) {
                if ($doc->getUuid()->equals($targetUuid)) {
                    $targetDoc = $doc;
                    break;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('UUID de document no vàlid', ['uuid' => $message->mediaUuid]);
        }

        if (!$targetDoc) {
            $this->logger->warning('Document no trobat dins de l\'ítem', ['uuid' => $message->mediaUuid]);
            return;
        }

        // Llegir el contingut binari des de Flysystem
        try {
            $content = $this->storageService->readDocumentContent($targetDoc);
        } catch (\Throwable $e) {
            $this->logger->error('Error llegint el fitxer des de Flysystem', [
                'error' => $e->getMessage(),
                'file_path' => $targetDoc->getFilePath(),
            ]);
            return;
        }

        // Determinar el tipus de prompt
        $promptType = 'photo';
        if ($targetDoc->getType() === DocumentType::INVOICE_RECEIPT
            || $targetDoc->getType() === DocumentType::PURCHASE_PROOF
            || $message->mediaType === 'invoice') {
            $promptType = 'invoice';
        }

        // Executar anàlisi amb SGLang
        $extraction = $this->aiAnalyzer->analyzeMedia($content, $targetDoc->getMimeType(), $promptType);

        if ($extraction->confidence > 0.0) {
            $extractedData = $extraction->toArray();
            $targetDoc->setAiMetadata($extractedData);

            // Fusionar dades de l'ítem
            $currentData = $item->getAiExtractedData() ?? [];
            $merged = array_merge($currentData, array_filter($extractedData, fn($val) => $val !== null));
            $item->setAiExtractedData($merged);

            $this->entityManager->flush();

            $this->logger->info('Anàlisi asíncrona completada i persistida amb èxit', [
                'item_id' => $item->getId(),
                'document_uuid' => $targetDoc->getUuid()->toRfc4122(),
                'confidence' => $extraction->confidence,
            ]);
        } else {
            $this->logger->notice('Anàlisi asíncrona finalitzada sense certesa o model no disponible', [
                'notes' => $extraction->notes,
            ]);
        }
    }
}
