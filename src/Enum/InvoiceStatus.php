<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Estat administratiu i de cobrament d'una factura.
 */
enum InvoiceStatus: string
{
    case ISSUED = 'issued';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Emesa',
            self::PAID => 'Cobrada',
            self::CANCELLED => 'Anul·lada',
            self::REFUNDED => 'Abonada',
        };
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }
}

