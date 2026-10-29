<?php

declare(strict_types=1);

namespace App\Enum;

enum CertificateMode: string
{
    case DRAFT = 'draft';
    case OFFICIAL = 'official';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Esborrany informatiu',
            self::OFFICIAL => 'Certificat oficial segellat',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    public function isOfficial(): bool
    {
        return $this === self::OFFICIAL;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-steel/15 text-steel border-steel/30',
            self::OFFICIAL => 'bg-gold/20 text-ink border-gold/40',
        };
    }
}
