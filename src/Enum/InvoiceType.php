<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Tipologia tributària de factura segons l'AEAT / SII / VeriFactu (RD 1619/2012).
 */
enum InvoiceType: string
{
    case F1_COMPLETE = 'F1';       // Factura ordinària completa amb dades fiscals NIF + adreça
    case F2_SIMPLIFIED = 'F2';     // Factura simplificada per a consumidors finals / particulars
    case R1_RECTIFICATION = 'R1';  // Factura rectificativa per error o devolució/abonament
    case R4_RECTIFICATION = 'R4';  // Factura rectificativa per altres motius legals

    public function label(): string
    {
        return match ($this) {
            self::F1_COMPLETE => 'Factura Completa (F1)',
            self::F2_SIMPLIFIED => 'Factura Simplificada (F2)',
            self::R1_RECTIFICATION => 'Factura Rectificativa / Abonament (R1)',
            self::R4_RECTIFICATION => 'Factura Rectificativa (R4)',
        };
    }

    public function isRectification(): bool
    {
        return $this === self::R1_RECTIFICATION || $this === self::R4_RECTIFICATION;
    }
}

