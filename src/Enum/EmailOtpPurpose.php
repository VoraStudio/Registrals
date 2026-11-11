<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Finalitat del codi temporal de 6 dígits enviat per correu electrònic.
 */
enum EmailOtpPurpose: string
{
    case REGISTRATION_VERIFY = 'registration_verify';
    case LOGIN_2FA = 'login_2fa';

    public function pageTitle(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Verifica el teu Correu — Registrals',
            self::LOGIN_2FA => 'Verificació en Dos Passos (2FA) — Registrals',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Verifica el teu Correu',
            self::LOGIN_2FA => 'Verificació en Dos Passos',
        };
    }

    public function subtitle(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Introdueix el codi de 6 dígits per validar el teu registre',
            self::LOGIN_2FA => 'Introdueix el codi de 6 dígits enviat al teu correu per accedir',
        };
    }

    public function emailSubject(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Codi de verificació de registre — Registrals',
            self::LOGIN_2FA => 'Codi d\'accés (2FA) — Registrals',
        };
    }

    public function emailHeading(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Validació del Compte Registrals',
            self::LOGIN_2FA => 'Codi de Seguretat d\'Accés (2FA)',
        };
    }

    public function emailIntro(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Per completar el teu registre i activar el teu espai de custòdia a <strong>Registrals</strong>, introdueix aquest codi numèric de verificació:',
            self::LOGIN_2FA => 'Hem detectat un inici de sessió al teu compte de <strong>Registrals</strong>. Introdueix aquest codi de verificació en dos passos per autoritzar l\'accés:',
        };
    }

    public function emailPlainIntro(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Per completar el teu registre i activar el teu compte a Registrals, introdueix aquest codi numèric de verificació:',
            self::LOGIN_2FA => 'Hem detectat un inici de sessió al teu compte de Registrals. Introdueix aquest codi de verificació en dos passos per autoritzar l\'accés:',
        };
    }

    public function submitButtonLabel(): string
    {
        return match ($this) {
            self::REGISTRATION_VERIFY => 'Validar Correu i Continuar',
            self::LOGIN_2FA => 'Confirmar Accés Segur',
        };
    }
}
