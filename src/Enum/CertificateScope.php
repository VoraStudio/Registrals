<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Àmbit patrimonial del certificat emès: bé individual o inventari global de llegat digital.
 */
enum CertificateScope: string
{
    case ITEM = 'item';
    case LEGACY_INVENTORY = 'legacy_inventory';

    public function label(): string
    {
        return match ($this) {
            self::ITEM => 'Certificat individual de bé',
            self::LEGACY_INVENTORY => 'Inventari Global de Llegat Digital',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::ITEM => 'bg-cream text-ink border-steel/30',
            self::LEGACY_INVENTORY => 'bg-ink text-gold border-gold/50',
        };
    }

    public function isItem(): bool
    {
        return $this === self::ITEM;
    }

    public function isLegacyInventory(): bool
    {
        return $this === self::LEGACY_INVENTORY;
    }
}

