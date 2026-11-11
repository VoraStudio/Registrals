<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Estat del cicle de vida i consentiment d'una persona de confiança designada (Art. 96 LOPDGDD).
 */
enum LegacyContactStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case REVOKED = 'revoked';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendent d\'acceptació',
            self::ACCEPTED => 'Acceptat i actiu',
            self::DECLINED => 'Refusat pel contacte',
            self::REVOKED => 'Revocat pel titular',
            self::EXPIRED => 'Invitació caducada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/15 text-amber-800 border-amber-500/30',
            self::ACCEPTED => 'bg-emerald-500/15 text-emerald-800 border-emerald-500/30',
            self::DECLINED => 'bg-rose-500/15 text-rose-800 border-rose-500/30',
            self::REVOKED => 'bg-slate-500/15 text-slate-700 border-slate-500/30',
            self::EXPIRED => 'bg-stone-500/15 text-stone-700 border-stone-500/30',
        };
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isAccepted(): bool
    {
        return $this === self::ACCEPTED;
    }

    public function isTerminated(): bool
    {
        return in_array($this, [self::DECLINED, self::REVOKED, self::EXPIRED], true);
    }
}

