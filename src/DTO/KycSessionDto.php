<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object per a l'obertura d'una sessió de verificació eIDV (Namirial OnBoarding).
 */
final readonly class KycSessionDto
{
    public function __construct(
        public string $requestId,
        public string $referenceId,
        public string $wizardUrl,
        public bool $isSimulated = false,
        public ?string $rawResponse = null
    ) {
    }
}

