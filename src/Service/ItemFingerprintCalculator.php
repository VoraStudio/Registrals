<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Item;
use App\Entity\ItemDocument;

/**
 * Servei de domini responsable de calcular el fingerprint criptogràfic determinista (SHA-256)
 * segons l'especificació d'integritat notarial de Registrals.
 */
class ItemFingerprintCalculator
{
    /**
     * Calcula el fingerprint mestre d'un bé a partir de les seves dades i proves actuals.
     *
     * Format del payload:
     * hash("sha256", cui | owner_uuid | doc_hashes_ordenats | timestamp)
     */
    public function calculateForItem(Item $item, ?\DateTimeImmutable $timestamp = null): string
    {
        $cui = $item->getCui();
        $ownerUuid = $item->getOwner()->getUuid()->toRfc4122();

        $documentHashes = [];
        foreach ($item->getDocuments() as $document) {
            $hash = $document->getSha256Hash();
            if ($hash !== '') {
                $documentHashes[] = $hash;
            }
        }

        $effectiveTimestamp = ($timestamp ?? $item->getUpdatedAt())->format(\DateTimeInterface::ATOM);

        return $this->calculateRaw($cui, $ownerUuid, $documentHashes, $effectiveTimestamp);
    }

    /**
     * Càlcul determinista pur a partir dels valors primitius.
     *
     * @param list<string> $documentHashes
     */
    public function calculateRaw(string $cui, string $ownerUuid, array $documentHashes, string $timestamp): string
    {
        // Ordenació alfabètica estricta per garantir determinisme absolut
        sort($documentHashes, SORT_STRING);

        $payload = sprintf(
            '%s|%s|%s|%s',
            $cui,
            $ownerUuid,
            implode(',', $documentHashes),
            $timestamp
        );

        return hash('sha256', $payload);
    }
}
