<?php

declare(strict_types=1);

namespace App\Service\Validation;

class MediaValidationResult
{
    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly bool $isAiGenerated,
        public readonly ?string $rejectionReason = null,
        public readonly ?string $detectedGenerator = null,
        public readonly array $metadata = [],
        public readonly array $warnings = [],
    ) {
    }

    public static function valid(array $metadata = [], array $warnings = []): self
    {
        return new self(
            isValid: true,
            isAiGenerated: false,
            rejectionReason: null,
            detectedGenerator: null,
            metadata: $metadata,
            warnings: $warnings
        );
    }

    public static function rejectedAi(string $reason, ?string $detectedGenerator = null): self
    {
        return new self(
            isValid: false,
            isAiGenerated: true,
            rejectionReason: $reason,
            detectedGenerator: $detectedGenerator
        );
    }

    public static function rejected(string $reason): self
    {
        return new self(
            isValid: false,
            isAiGenerated: false,
            rejectionReason: $reason
        );
    }
}
