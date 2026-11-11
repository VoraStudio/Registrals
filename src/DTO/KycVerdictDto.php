<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\KycStatus;

/**
 * Data Transfer Object amb la resolució verificada d'una sol·licitud eIDV a Namirial OnBoarding.
 */
final readonly class KycVerdictDto
{
    /**
     * @param array<string, mixed> $rawPayload
     */
    public function __construct(
        public string $requestId,
        public ?string $referenceId,
        public KycStatus $status,
        public string $providerStatus,
        public ?string $reason = null,
        public ?string $verifiedFirstName = null,
        public ?string $verifiedLastName = null,
        public ?float $biometricMatchScore = null,
        public bool $isSimulated = false,
        public array $rawPayload = []
    ) {
    }
}

