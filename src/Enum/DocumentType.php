<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentType: string
{
    case PHOTO_PRIMARY = 'photo_primary';
    case PHOTO_DETAIL = 'photo_detail';
    case INVOICE_RECEIPT = 'invoice_receipt';
    case CERTIFICATE_AUTHENTICITY = 'certificate_authenticity';
    case POLICE_REPORT = 'police_report';
    case LEGAL_DECLARATION = 'legal_declaration';

    public function label(): string
    {
        return match ($this) {
            self::PHOTO_PRIMARY => 'Fotografia principal del bé',
            self::PHOTO_DETAIL => 'Fotografia de detall / número de sèrie',
            self::INVOICE_RECEIPT => 'Factura / Tiquet de compra',
            self::CERTIFICATE_AUTHENTICITY => 'Certificat d\'autenticitat o garantia',
            self::POLICE_REPORT => 'Denúncia policial',
            self::LEGAL_DECLARATION => 'Declaració jurada o acta notarial',
        };
    }

    public function isPhoto(): bool
    {
        return match ($this) {
            self::PHOTO_PRIMARY, self::PHOTO_DETAIL => true,
            default => false,
        };
    }

    public function isEvidence(): bool
    {
        return !$this->isPhoto();
    }
}
