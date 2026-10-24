<?php

declare(strict_types=1);

namespace App\Service\Storage;

use App\Entity\Item;
use App\Entity\ItemDocument;
use App\Enum\DocumentType;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Servei de custòdia d'arxius privats fora de l'arrel web mitjançant Flysystem.
 * Calcula el hash SHA-256 criptogràfic i emmagatzema metadades estructurades.
 */
class SecureDocumentStorageService
{
    public function __construct(
        private readonly FilesystemOperator $itemDocumentStorage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Desa un fitxer pujat a l'emmagatzematge protegit i crea l'entitat ItemDocument.
     */
    public function storeUploadedDocument(
        Item $item,
        UploadedFile $uploadedFile,
        DocumentType $type,
        ?array $aiMetadata = null
    ): ItemDocument {
        $realPath = $uploadedFile->getRealPath();
        if ($realPath === false || !file_exists($realPath)) {
            throw new \RuntimeException('No s\'ha pogut accedir al fitxer temporal pujat.');
        }

        // 1. Càlcul obligatori de la traça criptogràfica SHA-256
        $sha256Hash = hash_file('sha256', $realPath);
        if ($sha256Hash === false) {
            throw new \RuntimeException('Error calculant el hash criptogràfic SHA-256 del fitxer.');
        }

        $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension() ?? 'bin';
        $originalFilename = $uploadedFile->getClientOriginalName();
        $mimeType = $uploadedFile->getClientMimeType();
        $fileSize = $uploadedFile->getSize();

        // 2. Ruta aïllada per ítem dins del volum protegit: {CUI}/{uuid}.{ext}
        $storageFilename = sprintf('%s/%s_%s.%s', $item->getCui(), date('YmdHis'), bin2hex(random_bytes(6)), $extension);

        // 3. Escriptura a través de Flysystem mitjançant stream
        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('No s\'ha pogut obrir el flux del fitxer per a l\'escriptura.');
        }

        try {
            $this->itemDocumentStorage->writeStream($storageFilename, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // 4. Creació de l'entitat ItemDocument
        $document = new ItemDocument(
            $item,
            $type,
            $storageFilename,
            $originalFilename,
            $mimeType,
            $fileSize,
            $sha256Hash
        );

        if ($aiMetadata !== null) {
            $document->setAiMetadata($aiMetadata);
        }

        $item->addDocument($document);

        $this->entityManager->persist($document);

        return $document;
    }

    /**
     * Llegeix el contingut del document des del magatzem protegit com a resource stream.
     *
     * @return resource
     */
    public function readDocumentStream(ItemDocument $document)
    {
        return $this->itemDocumentStorage->readStream($document->getFilePath());
    }

    /**
     * Retorna la mida real en bytes de l'arxiu custodiat.
     */
    public function getDocumentSize(ItemDocument $document): int
    {
        return $this->itemDocumentStorage->fileSize($document->getFilePath());
    }

    /**
     * Esborra un arxiu físic del magatzem privat.
     */
    public function deleteDocumentFile(ItemDocument $document): void
    {
        if ($this->itemDocumentStorage->fileExists($document->getFilePath())) {
            $this->itemDocumentStorage->delete($document->getFilePath());
        }
    }
}
