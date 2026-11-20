<?php

declare(strict_types=1);

namespace App\Enum;

enum ServiceProviderCategory: string
{
    case AI = 'ai';
    case PAYMENT = 'payment';
    case KYC = 'kyc';
    case BLOCKCHAIN = 'blockchain';
    case TSP = 'tsp';

    public function label(): string
    {
        return match ($this) {
            self::AI => 'IA Sobirana & Visió',
            self::PAYMENT => 'Passarel·la de Pagament',
            self::KYC => 'Verificació d\'Identitat (KYC / eIDV)',
            self::BLOCKCHAIN => 'Ancoratge Blockchain (L2)',
            self::TSP => 'Marca de Temps Qualificada (QTSP)',
        };
    }

    public function defaultPublicName(): string
    {
        return match ($this) {
            self::AI => 'Assistència de Lectura Sobirana',
            self::PAYMENT => 'Stripe',
            self::KYC => 'Namirial eIDV',
            self::BLOCKCHAIN => 'Polygon PoS (Layer 2)',
            self::TSP => 'Signaturit / Namirial (QTSP)',
        };
    }

    public function defaultProviderCode(): string
    {
        return match ($this) {
            self::AI => 'sglang',
            self::PAYMENT => 'stripe',
            self::KYC => 'namirial',
            self::BLOCKCHAIN => 'polygon-pos',
            self::TSP => 'signaturit_mock',
        };
    }
}

