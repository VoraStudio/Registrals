<?php

declare(strict_types=1);

namespace App\Enum;

enum BlockchainActionType: string
{
    case ITEM_REGISTRATION = 'item_registration';
    case ITEM_UPDATE = 'item_update';
    case TRANSFER = 'transfer';
    case STOLEN_FLAG = 'stolen_flag';
    case CERTIFICATE_ISSUED = 'certificate_issued';

    public function label(): string
    {
        return match ($this) {
            self::ITEM_REGISTRATION => 'Alta inicial de bé',
            self::ITEM_UPDATE => 'Actualització de dades / proves',
            self::TRANSFER => 'Traspàs de titularitat',
            self::STOLEN_FLAG => 'Alerta de robatori',
            self::CERTIFICATE_ISSUED => 'Emissió de certificat oficial',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::ITEM_REGISTRATION => 'bg-emerald-500/15 text-emerald-700 border-emerald-500/30',
            self::ITEM_UPDATE => 'bg-amber-500/15 text-amber-700 border-amber-500/30',
            self::TRANSFER => 'bg-blue-500/15 text-blue-700 border-blue-500/30',
            self::STOLEN_FLAG => 'bg-red-500/15 text-red-700 border-red-500/30',
            self::CERTIFICATE_ISSUED => 'bg-gold/20 text-ink border-gold/40',
        };
    }
}
