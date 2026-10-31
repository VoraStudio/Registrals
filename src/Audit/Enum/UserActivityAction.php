<?php

declare(strict_types=1);

namespace App\Audit\Enum;

enum UserActivityAction: string
{
    case LOGIN = 'login';
    case LOGIN_FAILED = 'login_failed';
    case LOGOUT = 'logout';
    case REGISTER = 'register';
    case PASSWORD_CHANGED = 'password_changed';
    case ITEM_CREATED = 'item_created';
    case ITEM_UPDATED = 'item_updated';
    case ITEM_DELETED = 'item_deleted';
    case STATUS_CHANGED = 'status_changed';
    case CERTIFICATE_ISSUED = 'certificate_issued';
    case CERTIFICATE_VERIFIED = 'certificate_verified';
    case TRANSFER_INITIATED = 'transfer_initiated';
    case TRANSFER_ACCEPTED = 'transfer_accepted';
    case TRANSFER_CANCELLED = 'transfer_cancelled';
    case TRANSFER_REJECTED = 'transfer_rejected';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Inici de sessió',
            self::LOGIN_FAILED => 'Intent fallit d\'accés',
            self::LOGOUT => 'Tancament de sessió',
            self::REGISTER => 'Registre de compte',
            self::PASSWORD_CHANGED => 'Canvi de contrasenya',
            self::ITEM_CREATED => 'Creació de bé',
            self::ITEM_UPDATED => 'Modificació de bé',
            self::ITEM_DELETED => 'Eliminació de bé',
            self::STATUS_CHANGED => 'Canvi d\'estat del compte',
            self::CERTIFICATE_ISSUED => 'Emissió de certificat',
            self::CERTIFICATE_VERIFIED => 'Verificació de certificat',
            self::TRANSFER_INITIATED => 'Inici de traspàs',
            self::TRANSFER_ACCEPTED => 'Acceptació de traspàs',
            self::TRANSFER_CANCELLED => 'Cancel·lació de traspàs',
            self::TRANSFER_REJECTED => 'Rebuig de traspàs',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::LOGIN => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::LOGIN_FAILED => 'bg-rose-100 text-rose-900 border-rose-300 font-bold',
            self::LOGOUT => 'bg-slate-100 text-slate-700 border-slate-300',
            self::REGISTER => 'bg-indigo-50 text-indigo-800 border-indigo-300',
            self::PASSWORD_CHANGED => 'bg-amber-50 text-amber-800 border-amber-300',
            self::ITEM_CREATED => 'bg-cyan-50 text-cyan-800 border-cyan-300',
            self::ITEM_UPDATED => 'bg-blue-50 text-blue-800 border-blue-300',
            self::ITEM_DELETED => 'bg-rose-50 text-rose-800 border-rose-300',
            self::STATUS_CHANGED => 'bg-amber-50 text-amber-900 border-amber-400',
            self::CERTIFICATE_ISSUED => 'bg-gold/20 text-ink border-gold/40',
            self::CERTIFICATE_VERIFIED => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::TRANSFER_INITIATED, self::TRANSFER_ACCEPTED => 'bg-purple-50 text-purple-800 border-purple-300',
            self::TRANSFER_CANCELLED, self::TRANSFER_REJECTED => 'bg-slate-100 text-slate-700 border-slate-300',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this) {
            self::LOGIN,
            self::LOGIN_FAILED,
            self::LOGOUT,
            self::REGISTER,
            self::PASSWORD_CHANGED,
            self::STATUS_CHANGED => 'Usuaris & Seguretat',

            self::ITEM_CREATED,
            self::ITEM_UPDATED,
            self::ITEM_DELETED => 'Patrimoni & Béns',

            self::CERTIFICATE_ISSUED,
            self::CERTIFICATE_VERIFIED => 'Certificats Digitals',

            self::TRANSFER_INITIATED,
            self::TRANSFER_ACCEPTED,
            self::TRANSFER_CANCELLED,
            self::TRANSFER_REJECTED => 'Transmissions C2C',
        };
    }

    /**
     * Retorna les accions organitzades a 2 nivells per categoria conceptual per a selectors (optgroup).
     *
     * @return array<string, list<self>>
     */
    public static function groupedByCategory(): array
    {
        return [
            'Usuaris & Seguretat' => [
                self::LOGIN,
                self::LOGIN_FAILED,
                self::LOGOUT,
                self::REGISTER,
                self::PASSWORD_CHANGED,
                self::STATUS_CHANGED,
            ],
            'Patrimoni & Béns' => [
                self::ITEM_CREATED,
                self::ITEM_UPDATED,
                self::ITEM_DELETED,
            ],
            'Certificats Digitals' => [
                self::CERTIFICATE_ISSUED,
                self::CERTIFICATE_VERIFIED,
            ],
            'Transmissions C2C' => [
                self::TRANSFER_INITIATED,
                self::TRANSFER_ACCEPTED,
                self::TRANSFER_CANCELLED,
                self::TRANSFER_REJECTED,
            ],
        ];
    }
}
