<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentType: string
{
    case SUBSCRIPTION = 'subscription';
    case CERTIFICATE = 'certificate';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::SUBSCRIPTION => 'Subscripció Anual de Pla',
            self::CERTIFICATE => 'Emissió de Certificat Oficial Solt',
            self::TRANSFER => 'Taxa de Traspàs Notarial C2C',
        };
    }
}
