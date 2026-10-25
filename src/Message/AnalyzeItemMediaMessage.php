<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Missatge asíncron per processar la visió per computador o OCR d'un fitxer mitjançant SGLang.
 */
final readonly class AnalyzeItemMediaMessage
{
    public function __construct(
        public int $itemId,
        public string $mediaUuid,
        public string $mediaType = 'photo', // 'photo' | 'invoice' | 'evidence'
        public string $context = 'wizard',  // 'wizard' | 'item_show'
    ) {
    }
}
