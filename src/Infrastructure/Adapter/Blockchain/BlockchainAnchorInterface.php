<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Blockchain;

use App\DTO\BlockchainReceiptDto;

/**
 * Port del domini per a la notarització criptogràfica a xarxa Blockchain Layer 2 (Polygon PoS / Base).
 */
interface BlockchainAnchorInterface
{
    /**
     * Calcula el fingerprint SHA-256 determinista per a un bé i el conjunt de proves.
     *
     * @param string $cui Codi Únic d'Identificació (ex: REG-2026-A4B7C9)
     * @param string $ownerUuid UUID v7 del titular
     * @param list<string> $documentHashes Llista de hashes SHA-256 de les fotos i documents probatoris
     * @param string $timestamp Data de creació o darrera actualització en format ISO 8601 (ATOM)
     */
    public function calculateItemFingerprint(string $cui, string $ownerUuid, array $documentHashes, string $timestamp): string;

    /**
     * Ancola el hash SHA-256 a la xarxa Blockchain mitjançant transacció Layer 2.
     *
     * @param string $cui CUI de l'ítem
     * @param string $sha256Hash Hash determinista que s'immutabilitza
     * @return BlockchainReceiptDto
     */
    public function anchorHash(string $cui, string $sha256Hash): BlockchainReceiptDto;
}
