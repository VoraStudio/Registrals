<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Ai;

use App\DTO\AiVisionExtractionDto;

/**
 * Port del domini per a l'anàlisi de visió per computador i OCR d'imatges/documents.
 */
interface AiVisionAnalyzerInterface
{
    /**
     * Analitza una imatge de bé o un document (tiquet/factura/garantia) i retorna dades estructurades.
     *
     * @param string $fileContent Contingut binari de la imatge o PDF
     * @param string $mimeType Tipus MIME (ex: image/jpeg, image/png, application/pdf)
     * @param string $promptType Tipus de prompt ('photo' per a objectes o 'invoice' per a tiquets/factures)
     * @return AiVisionExtractionDto
     */
    public function analyzeMedia(string $fileContent, string $mimeType, string $promptType = 'photo'): AiVisionExtractionDto;
}
