<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter\Signature;

use App\DTO\SignaturitTimestampDto;

/**
 * Port del domini per al segellat de temps qualificat eIDAS (TSP).
 */
interface SignaturitTimestampInterface
{
    /**
     * Emet un segell de temps qualificat eIDAS per a un hash SHA-256.
     *
     * @param string $sha256Hash Hash del document o bé a segellar
     * @return SignaturitTimestampDto Rebut del segellat eIDAS
     */
    public function timestampHash(string $sha256Hash): SignaturitTimestampDto;
}
