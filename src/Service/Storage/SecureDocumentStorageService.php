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

        $origRealPath = $realPath;
        $origSha256 = hash_file('sha256', $origRealPath);
        $origSize = $uploadedFile->getSize();
        $origMime = $uploadedFile->getClientMimeType();

        $processedPath = $origRealPath;
        $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension() ?? 'bin';
        $originalFilename = $uploadedFile->getClientOriginalName();
        $mimeType = $origMime;
        $tempPathToClean = null;

        // Pipeline d'ingestió fotogràfica: optimització segura a WebP màxim 2048px per a Flysystem
        if ($type->isPhoto()) {
            $opt = $this->optimizePhotoToWebp($origRealPath, $origMime);
            if ($opt['isTransformed']) {
                $processedPath = $opt['path'];
                $tempPathToClean = $opt['path'];
                $mimeType = $opt['mimeType'];
                $extension = $opt['extension'];

                $aiMetadata ??= [];
                $aiMetadata['originalSha256'] = $origSha256;
                $aiMetadata['originalFileSize'] = $origSize;
                $aiMetadata['originalDimensions'] = [
                    'width' => $opt['origWidth'],
                    'height' => $opt['origHeight'],
                ];
                $aiMetadata['scaledDimensions'] = [
                    'width' => $opt['newWidth'],
                    'height' => $opt['newHeight'],
                ];
            }
        }

        // 1. Càlcul obligatori de la traça criptogràfica SHA-256 de l'arxiu custodiat
        $sha256Hash = hash_file('sha256', $processedPath);
        if ($sha256Hash === false) {
            throw new \RuntimeException('Error calculant el hash criptogràfic SHA-256 del fitxer.');
        }

        $fileSize = filesize($processedPath) ?: $origSize;

        // 2. Ruta aïllada per ítem dins del volum protegit: {CUI}/{uuid}.{ext}
        $storageFilename = sprintf('%s/%s_%s.%s', $item->getCui(), date('YmdHis'), bin2hex(random_bytes(6)), $extension);

        // 3. Escriptura a través de Flysystem mitjançant stream
        $stream = fopen($processedPath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('No s\'ha pogut obrir el flux del fitxer per a l\'escriptura.');
        }

        try {
            $this->itemDocumentStorage->writeStream($storageFilename, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if ($tempPathToClean !== null && file_exists($tempPathToClean)) {
                @unlink($tempPathToClean);
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
     * Optimitza i escala una fotografia a WebP (màxim 2048px) preservant la qualitat probatòria.
     * Si no és una imatge o falla el processament, retorna el fitxer original sense alterar.
     *
     * @return array{path: string, mimeType: string, extension: string, isTransformed: bool, origWidth?: int, origHeight?: int, newWidth?: int, newHeight?: int}
     */
    private function optimizePhotoToWebp(string $sourcePath, string $mimeType): array
    {
        if (!function_exists('imagewebp') || !str_starts_with($mimeType, 'image/')) {
            return ['path' => $sourcePath, 'mimeType' => $mimeType, 'extension' => pathinfo($sourcePath, PATHINFO_EXTENSION), 'isTransformed' => false];
        }

        if ($mimeType === 'image/svg+xml') {
            return ['path' => $sourcePath, 'mimeType' => $mimeType, 'extension' => 'svg', 'isTransformed' => false];
        }

        $image = null;
        try {
            $image = match ($mimeType) {
                'image/jpeg' => @imagecreatefromjpeg($sourcePath),
                'image/png' => @imagecreatefrompng($sourcePath),
                'image/webp' => @imagecreatefromwebp($sourcePath),
                default => null,
            };

            if (!$image) {
                return ['path' => $sourcePath, 'mimeType' => $mimeType, 'extension' => pathinfo($sourcePath, PATHINFO_EXTENSION), 'isTransformed' => false];
            }

            $origWidth = imagesx($image);
            $origHeight = imagesy($image);

            $maxDim = 2048;
            $newWidth = $origWidth;
            $newHeight = $origHeight;

            if ($origWidth > $maxDim || $origHeight > $maxDim) {
                if ($origWidth >= $origHeight) {
                    $newWidth = $maxDim;
                    $newHeight = (int) round($origHeight * ($maxDim / $origWidth));
                } else {
                    $newHeight = $maxDim;
                    $newWidth = (int) round($origWidth * ($maxDim / $origHeight));
                }
            }

            if ($mimeType === 'image/webp' && $newWidth === $origWidth && $newHeight === $origHeight) {
                imagedestroy($image);
                return ['path' => $sourcePath, 'mimeType' => 'image/webp', 'extension' => 'webp', 'isTransformed' => false];
            }

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

            $tempWebpPath = tempnam(sys_get_temp_dir(), 'reg_webp_');
            if ($tempWebpPath === false) {
                imagedestroy($image);
                imagedestroy($resized);
                return ['path' => $sourcePath, 'mimeType' => $mimeType, 'extension' => pathinfo($sourcePath, PATHINFO_EXTENSION), 'isTransformed' => false];
            }

            imagewebp($resized, $tempWebpPath, 88);

            imagedestroy($image);
            imagedestroy($resized);

            return [
                'path' => $tempWebpPath,
                'mimeType' => 'image/webp',
                'extension' => 'webp',
                'isTransformed' => true,
                'origWidth' => $origWidth,
                'origHeight' => $origHeight,
                'newWidth' => $newWidth,
                'newHeight' => $newHeight,
            ];
        } catch (\Throwable) {
            if ($image) imagedestroy($image);
            return ['path' => $sourcePath, 'mimeType' => $mimeType, 'extension' => pathinfo($sourcePath, PATHINFO_EXTENSION), 'isTransformed' => false];
        }
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
     * Llegeix el contingut binari complet del document des del magatzem protegit.
     */
    public function readDocumentContent(ItemDocument $document): string
    {
        return $this->itemDocumentStorage->read($document->getFilePath());
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
