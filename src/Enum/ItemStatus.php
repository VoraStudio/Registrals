<?php

declare(strict_types=1);

namespace App\Enum;

enum ItemStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case TRANSFER_PENDING = 'transfer_pending';
    case TRANSFERRED = 'transferred';
    case STOLEN = 'stolen';
    case LOST = 'lost';
    case ARCHIVED = 'archived';
    case NEEDS_REVIEW = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Esborrany',
            self::ACTIVE => 'Custodiat / Actiu',
            self::TRANSFER_PENDING => 'Traspàs en curs',
            self::TRANSFERRED => 'Traspassat',
            self::STOLEN => 'Denunciat per robatori',
            self::LOST => 'Perdut',
            self::ARCHIVED => 'Arxivat',
            self::NEEDS_REVIEW => 'Revisió pendent (Lot modificat)',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-steel/15 text-steel border-steel/30',
            self::ACTIVE => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
            self::TRANSFER_PENDING => 'bg-amber-500/15 text-amber-700 border-amber-500/30',
            self::TRANSFERRED => 'bg-blue-500/15 text-blue-700 border-blue-500/30',
            self::STOLEN => 'bg-red-500/15 text-red-700 border-red-500/30',
            self::LOST => 'bg-rose-500/15 text-rose-700 border-rose-500/30',
            self::ARCHIVED => 'bg-stone-500/15 text-stone-700 border-stone-500/30',
            self::NEEDS_REVIEW => 'bg-orange-500/15 text-orange-700 border-orange-500/30',
        };
    }
}
