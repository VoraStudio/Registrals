<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Data Transfer Object per a la resposta del segellat de temps qualificat eIDAS de Signaturit.
 */
final readonly class SignaturitTimestampDto
{
    public function __construct(
        public string $tokenId,
        public \DateTimeImmutable $timestamp,
        public string $provider = 'Signaturit Qualified Trust Service Provider (eIDAS EU 910/2014)',
        public bool $isCertified = true,
        public ?string $rawResponse = null
    ) {
    }

    public function getShortToken(): string
    {
        if (strlen($this->tokenId) <= 14) {
            return $this->tokenId;
        }

        return substr($this->tokenId, 0, 8) . '...' . substr($this->tokenId, -6);
    }
}
