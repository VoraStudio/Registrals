<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Encapsula la resposta i rebut tècnic d'una transacció d'ancoratge a Blockchain.
 */
final readonly class BlockchainReceiptDto
{
    public function __construct(
        public string $txHash,
        public int $blockNumber,
        public string $anchoredHash,
        public string $contractAddress,
        public string $network = 'polygon-pos',
        public \DateTimeImmutable $anchoredAt = new \DateTimeImmutable(),
        public bool $isSuccess = true,
        public ?string $errorMessage = null,
        public ?int $gasUsed = null
    ) {
    }

    public function getShortTxHash(): string
    {
        if (strlen($this->txHash) <= 14) {
            return $this->txHash;
        }

        return substr($this->txHash, 0, 8) . '...' . substr($this->txHash, -6);
    }
}
