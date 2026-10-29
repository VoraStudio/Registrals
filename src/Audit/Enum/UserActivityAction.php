<?php

declare(strict_types=1);

namespace App\Audit\Enum;

enum UserActivityAction: string
{
    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case REGISTER = 'register';
    case PASSWORD_CHANGED = 'password_changed';
    case ITEM_CREATED = 'item_created';
    case ITEM_UPDATED = 'item_updated';
    case ITEM_DELETED = 'item_deleted';
    case STATUS_CHANGED = 'status_changed';
    case CERTIFICATE_ISSUED = 'certificate_issued';
    case TRANSFER_INITIATED = 'transfer_initiated';
    case TRANSFER_ACCEPTED = 'transfer_accepted';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Inici de sessió',
            self::LOGOUT => 'Tancament de sessió',
            self::REGISTER => 'Registre de compte',
            self::PASSWORD_CHANGED => 'Canvi de contrasenya',
            self::ITEM_CREATED => 'Creació de bé',
            self::ITEM_UPDATED => 'Modificació de bé',
            self::ITEM_DELETED => 'Eliminació de bé',
            self::STATUS_CHANGED => 'Canvi d\'estat del compte',
            self::CERTIFICATE_ISSUED => 'Emissió de certificat',
            self::TRANSFER_INITIATED => 'Inici de traspàs',
            self::TRANSFER_ACCEPTED => 'Acceptació de traspàs',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::LOGIN => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::LOGOUT => 'bg-slate-100 text-slate-700 border-slate-300',
            self::REGISTER => 'bg-indigo-50 text-indigo-800 border-indigo-300',
            self::PASSWORD_CHANGED => 'bg-amber-50 text-amber-800 border-amber-300',
            self::ITEM_CREATED => 'bg-cyan-50 text-cyan-800 border-cyan-300',
            self::ITEM_UPDATED => 'bg-blue-50 text-blue-800 border-blue-300',
            self::ITEM_DELETED => 'bg-rose-50 text-rose-800 border-rose-300',
            self::STATUS_CHANGED => 'bg-amber-50 text-amber-900 border-amber-400',
            self::CERTIFICATE_ISSUED => 'bg-gold/20 text-ink border-gold/40',
            self::TRANSFER_INITIATED, self::TRANSFER_ACCEPTED => 'bg-purple-50 text-purple-800 border-purple-300',
        };
    }
}
