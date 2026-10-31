<?php

declare(strict_types=1);

namespace App\Enum;

enum TransferStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendent d\'acceptació',
            self::ACCEPTED => 'Acceptat i completat',
            self::REJECTED => 'Rebutjat pel destinatari',
            self::EXPIRED => 'Caducat (més de 72 h)',
            self::CANCELLED => 'Cancel·lat pel remitent',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/15 text-amber-700 border-amber-500/30',
            self::ACCEPTED => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
            self::REJECTED => 'bg-rose-500/15 text-rose-700 border-rose-500/30',
            self::EXPIRED => 'bg-stone-500/15 text-stone-700 border-stone-500/30',
            self::CANCELLED => 'bg-gray-500/15 text-gray-700 border-gray-500/30',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this === self::ACCEPTED;
    }

    public function isTerminated(): bool
    {
        return in_array($this, [self::ACCEPTED, self::REJECTED, self::EXPIRED, self::CANCELLED], true);
    }
}
