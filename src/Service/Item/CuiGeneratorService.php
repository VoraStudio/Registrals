<?php

declare(strict_types=1);

namespace App\Service\Item;

use App\Repository\ItemRepository;

/**
 * Genera codis CUI (Codi Únic d'Identificació notarial) per a béns mobles.
 * Format oficial de l'arquitectura: REG-{ANY}-{6_CARACTERS_ALFANUMERICS}
 * Ex: REG-2026-X8M2K9
 */
class CuiGeneratorService
{
    // Caràcters clars evitant confusió visual (sense O/0, I/1, L)
    private const CHARSET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
    private const LENGTH = 6;

    public function __construct(
        private readonly ItemRepository $itemRepository,
    ) {
    }

    /**
     * Genera un nou CUI garantint unicitat absoluta contra la base de dades.
     */
    public function generate(?int $year = null): string
    {
        $year ??= (int) date('Y');
        $charsetLen = strlen(self::CHARSET);

        do {
            $randomCode = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $randomCode .= self::CHARSET[random_int(0, $charsetLen - 1)];
            }

            $cui = sprintf('REG-%d-%s', $year, $randomCode);
        } while ($this->itemRepository->findOneByCui($cui) !== null);

        return $cui;
    }
}
