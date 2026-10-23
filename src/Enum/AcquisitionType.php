<?php

declare(strict_types=1);

namespace App\Enum;

enum AcquisitionType: string
{
    case PURCHASE = 'purchase';
    case INHERITANCE = 'inheritance';
    case GIFT = 'gift';
    case PRODUCTION = 'production';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Compra / Adquisició comercial',
            self::INHERITANCE => 'Herència / Successió',
            self::GIFT => 'Donació / Regal',
            self::PRODUCTION => 'Creació / Fabricació pròpia',
            self::OTHER => 'Altres circumstàncies',
        };
    }
}
