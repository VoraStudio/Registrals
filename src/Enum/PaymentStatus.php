<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case DISPUTED = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendent de Confirmació',
            self::PAID => 'Completat amb Èxit',
            self::FAILED => 'Cobrament Fallit',
            self::REFUNDED => 'Abonat / Retornat',
            self::DISPUTED => 'En Disputa Bancària',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::PAID;
    }
}
