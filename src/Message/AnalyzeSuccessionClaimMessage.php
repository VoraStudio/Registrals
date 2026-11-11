<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Missatge asíncron per executar el pre-triatge documental sobirà (Qwen-VL)
 * d'un expedient successori post-mortem custodiat a Flysystem.
 */
final readonly class AnalyzeSuccessionClaimMessage
{
    public function __construct(
        public int $claimId,
    ) {
    }
}

