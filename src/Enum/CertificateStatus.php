<?php

declare(strict_types=1);

namespace App\Enum;

enum CertificateStatus: string
{
    case VALID = 'valid';
    case SUPERSEDED = 'superseded';
    case REVOKED = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::VALID => 'Certificat vigent',
            self::SUPERSEDED => 'Superat per nova versió (v2)',
            self::REVOKED => 'Revocat / Anul·lat',
        };
    }

    public function isValid(): bool
    {
        return $this === self::VALID;
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::VALID => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
            self::SUPERSEDED => 'bg-amber-500/15 text-amber-700 border-amber-500/30',
            self::REVOKED => 'bg-red-500/15 text-red-700 border-red-500/30',
        };
    }
}
