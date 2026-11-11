<?php

declare(strict_types=1);

namespace App\Enum;

enum PaymentType: string
{
    case SUBSCRIPTION = 'subscription';
    case CERTIFICATE = 'certificate';
    case TRANSFER = 'transfer';
    case SUCCESSION_CLAIM = 'succession_claim';
    case KYC = 'kyc';

    public function label(): string
    {
        return match ($this) {
            self::SUBSCRIPTION => 'Subscripció Anual de Pla',
            self::CERTIFICATE => 'Emissió de Certificat Oficial Solt',
            self::TRANSFER => 'Taxa de Traspàs C2C',
            self::SUCCESSION_CLAIM => 'Taxa d\'Obertura d\'Expedient Successori',
            self::KYC => 'Verificació d\'Identitat (eIDV / KYC)',
        };
    }
}
