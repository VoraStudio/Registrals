<?php

declare(strict_types=1);

namespace App\Audit\Enum;

enum UserActivityAction: string
{
    case LOGIN = 'login';
    case LOGIN_FAILED = 'login_failed';
    case LOGOUT = 'logout';
    case REGISTER = 'register';
    case EMAIL_VERIFIED = 'email_verified';
    case PASSWORD_CHANGED = 'password_changed';
    case PASSWORD_RESET_REQUESTED = 'password_reset_requested';
    case TWO_FACTOR_CHALLENGE_SENT = 'two_factor_challenge_sent';
    case TWO_FACTOR_VERIFIED = 'two_factor_verified';
    case TWO_FACTOR_FAILED = 'two_factor_failed';
    case TWO_FACTOR_TOGGLED = 'two_factor_toggled';
    case ITEM_CREATED = 'item_created';
    case ITEM_UPDATED = 'item_updated';
    case ITEM_DELETED = 'item_deleted';
    case STATUS_CHANGED = 'status_changed';
    case CERTIFICATE_ISSUED = 'certificate_issued';
    case CERTIFICATE_VERIFIED = 'certificate_verified';
    case LEGACY_CONTACT_DESIGNATED = 'legacy_contact_designated';
    case LEGACY_CONTACT_ACCEPTED = 'legacy_contact_accepted';
    case LEGACY_CONTACT_DECLINED = 'legacy_contact_declined';
    case LEGACY_CONTACT_REVOKED = 'legacy_contact_revoked';
    case LEGACY_INVENTORY_CERT_ISSUED = 'legacy_inventory_cert_issued';
    case SUCCESSION_CLAIM_SUBMITTED = 'succession_claim_submitted';
    case SUCCESSION_CLAIM_APPROVED = 'succession_claim_approved';
    case SUCCESSION_CLAIM_REJECTED = 'succession_claim_rejected';
    case TRANSFER_INITIATED = 'transfer_initiated';
    case TRANSFER_ACCEPTED = 'transfer_accepted';
    case TRANSFER_CANCELLED = 'transfer_cancelled';
    case TRANSFER_REJECTED = 'transfer_rejected';
    case TRANSFER_EXPIRED = 'transfer_expired';
    case PAYMENT_COMPLETED = 'payment_completed';
    case PAYMENT_FAILED = 'payment_failed';
    case SUBSCRIPTION_STARTED = 'subscription_started';
    case SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    case REFUND_PROCESSED = 'refund_processed';
    case LEGAL_TERMS_ACCEPTED = 'legal_terms_accepted';
    case KYC_INITIATED = 'kyc_initiated';
    case KYC_APPROVED = 'kyc_approved';
    case KYC_REJECTED = 'kyc_rejected';

    public function label(): string
    {
        return match ($this) {
            self::LOGIN => 'Inici de sessió',
            self::LOGIN_FAILED => 'Intent fallit d\'accés',
            self::LOGOUT => 'Tancament de sessió',
            self::REGISTER => 'Registre de compte',
            self::EMAIL_VERIFIED => 'Correu electrònic verificat',
            self::PASSWORD_CHANGED => 'Canvi de contrasenya',
            self::PASSWORD_RESET_REQUESTED => 'Sol·licitud de recuperació de contrasenya',
            self::TWO_FACTOR_CHALLENGE_SENT => 'Codi 2FA enviat per correu',
            self::TWO_FACTOR_VERIFIED => 'Accés 2FA verificat',
            self::TWO_FACTOR_FAILED => 'Verificació 2FA fallida',
            self::TWO_FACTOR_TOGGLED => 'Configuració 2FA actualitzada',
            self::ITEM_CREATED => 'Creació de bé',
            self::ITEM_UPDATED => 'Modificació de bé',
            self::ITEM_DELETED => 'Eliminació de bé',
            self::STATUS_CHANGED => 'Canvi d\'estat del compte',
            self::CERTIFICATE_ISSUED => 'Emissió de certificat',
            self::CERTIFICATE_VERIFIED => 'Verificació de certificat',
            self::LEGACY_CONTACT_DESIGNATED => 'Designació persona de confiança',
            self::LEGACY_CONTACT_ACCEPTED => 'Acceptació consentiment llegat',
            self::LEGACY_CONTACT_DECLINED => 'Rebuig designació llegat',
            self::LEGACY_CONTACT_REVOKED => 'Revocació persona de confiança',
            self::LEGACY_INVENTORY_CERT_ISSUED => 'Emissió certificat d\'inventari llegat',
            self::SUCCESSION_CLAIM_SUBMITTED => 'Obertura expedient successori',
            self::SUCCESSION_CLAIM_APPROVED => 'Aprovació traspàs successori',
            self::SUCCESSION_CLAIM_REJECTED => 'Rebuig expedient successori',
            self::TRANSFER_INITIATED => 'Inici de traspàs',
            self::TRANSFER_ACCEPTED => 'Acceptació de traspàs',
            self::TRANSFER_CANCELLED => 'Cancel·lació de traspàs',
            self::TRANSFER_REJECTED => 'Rebuig de traspàs',
            self::TRANSFER_EXPIRED => 'Caducitat de traspàs',
            self::PAYMENT_COMPLETED => 'Pagament completat',
            self::PAYMENT_FAILED => 'Pagament fallit',
            self::SUBSCRIPTION_STARTED => 'Subscripció activada',
            self::SUBSCRIPTION_CANCELLED => 'Subscripció cancel·lada',
            self::REFUND_PROCESSED => 'Reemborsament processat',
            self::LEGAL_TERMS_ACCEPTED => 'Acceptació de clàusules legals',
            self::KYC_INITIATED => 'Inici de verificació eIDV',
            self::KYC_APPROVED => 'Identitat verificada eIDV',
            self::KYC_REJECTED => 'Verificació eIDV rebutjada',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::LOGIN => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::LOGIN_FAILED => 'bg-rose-100 text-rose-900 border-rose-300 font-bold',
            self::LOGOUT => 'bg-slate-100 text-slate-700 border-slate-300',
            self::REGISTER => 'bg-indigo-50 text-indigo-800 border-indigo-300',
            self::EMAIL_VERIFIED,
            self::TWO_FACTOR_VERIFIED => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::TWO_FACTOR_CHALLENGE_SENT,
            self::TWO_FACTOR_TOGGLED,
            self::PASSWORD_CHANGED,
            self::PASSWORD_RESET_REQUESTED => 'bg-amber-50 text-amber-800 border-amber-300',
            self::TWO_FACTOR_FAILED => 'bg-rose-100 text-rose-900 border-rose-300 font-bold',
            self::LEGAL_TERMS_ACCEPTED => 'bg-blue-50 text-blue-800 border-blue-300 font-medium',
            self::KYC_INITIATED => 'bg-amber-50 text-amber-800 border-amber-300',
            self::KYC_APPROVED => 'bg-emerald-50 text-emerald-800 border-emerald-300 font-bold',
            self::KYC_REJECTED => 'bg-rose-100 text-rose-900 border-rose-300 font-bold',
            self::ITEM_CREATED => 'bg-cyan-50 text-cyan-800 border-cyan-300',
            self::ITEM_UPDATED => 'bg-blue-50 text-blue-800 border-blue-300',
            self::ITEM_DELETED => 'bg-rose-50 text-rose-800 border-rose-300',
            self::STATUS_CHANGED => 'bg-amber-50 text-amber-900 border-amber-400',
            self::CERTIFICATE_ISSUED => 'bg-gold/20 text-ink border-gold/40',
            self::CERTIFICATE_VERIFIED => 'bg-emerald-50 text-emerald-800 border-emerald-300',
            self::LEGACY_CONTACT_DESIGNATED,
            self::LEGACY_INVENTORY_CERT_ISSUED => 'bg-gold/20 text-ink border-gold/40 font-medium',
            self::LEGACY_CONTACT_ACCEPTED,
            self::SUCCESSION_CLAIM_APPROVED => 'bg-emerald-50 text-emerald-800 border-emerald-300 font-bold',
            self::LEGACY_CONTACT_DECLINED,
            self::LEGACY_CONTACT_REVOKED,
            self::SUCCESSION_CLAIM_REJECTED => 'bg-rose-50 text-rose-800 border-rose-300',
            self::SUCCESSION_CLAIM_SUBMITTED => 'bg-amber-50 text-amber-900 border-amber-400 font-medium',
            self::TRANSFER_INITIATED, self::TRANSFER_ACCEPTED => 'bg-purple-50 text-purple-800 border-purple-300',
            self::TRANSFER_CANCELLED, self::TRANSFER_REJECTED, self::TRANSFER_EXPIRED => 'bg-slate-100 text-slate-700 border-slate-300',
            self::PAYMENT_COMPLETED, self::SUBSCRIPTION_STARTED => 'bg-emerald-50 text-emerald-800 border-emerald-300 font-medium',
            self::PAYMENT_FAILED, self::SUBSCRIPTION_CANCELLED => 'bg-rose-50 text-rose-800 border-rose-300',
            self::REFUND_PROCESSED => 'bg-amber-50 text-amber-800 border-amber-300',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this) {
            self::LOGIN,
            self::LOGIN_FAILED,
            self::LOGOUT,
            self::REGISTER,
            self::EMAIL_VERIFIED,
            self::PASSWORD_CHANGED,
            self::PASSWORD_RESET_REQUESTED,
            self::TWO_FACTOR_CHALLENGE_SENT,
            self::TWO_FACTOR_VERIFIED,
            self::TWO_FACTOR_FAILED,
            self::TWO_FACTOR_TOGGLED,
            self::STATUS_CHANGED,
            self::LEGAL_TERMS_ACCEPTED,
            self::KYC_INITIATED,
            self::KYC_APPROVED,
            self::KYC_REJECTED => 'Usuaris & Seguretat',

            self::ITEM_CREATED,
            self::ITEM_UPDATED,
            self::ITEM_DELETED => 'Patrimoni & Béns',

            self::CERTIFICATE_ISSUED,
            self::CERTIFICATE_VERIFIED => 'Certificats Digitals',

            self::LEGACY_CONTACT_DESIGNATED,
            self::LEGACY_CONTACT_ACCEPTED,
            self::LEGACY_CONTACT_DECLINED,
            self::LEGACY_CONTACT_REVOKED,
            self::LEGACY_INVENTORY_CERT_ISSUED,
            self::SUCCESSION_CLAIM_SUBMITTED,
            self::SUCCESSION_CLAIM_APPROVED,
            self::SUCCESSION_CLAIM_REJECTED => 'Herència Digital & Llegats',

            self::TRANSFER_INITIATED,
            self::TRANSFER_ACCEPTED,
            self::TRANSFER_CANCELLED,
            self::TRANSFER_REJECTED,
            self::TRANSFER_EXPIRED => 'Transmissions C2C',

            self::PAYMENT_COMPLETED,
            self::PAYMENT_FAILED,
            self::SUBSCRIPTION_STARTED,
            self::SUBSCRIPTION_CANCELLED,
            self::REFUND_PROCESSED => 'Facturació & Pagaments',
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
                self::EMAIL_VERIFIED,
                self::PASSWORD_CHANGED,
                self::PASSWORD_RESET_REQUESTED,
                self::TWO_FACTOR_CHALLENGE_SENT,
                self::TWO_FACTOR_VERIFIED,
                self::TWO_FACTOR_FAILED,
                self::TWO_FACTOR_TOGGLED,
                self::STATUS_CHANGED,
                self::LEGAL_TERMS_ACCEPTED,
                self::KYC_INITIATED,
                self::KYC_APPROVED,
                self::KYC_REJECTED,
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
            'Herència Digital & Llegats' => [
                self::LEGACY_CONTACT_DESIGNATED,
                self::LEGACY_CONTACT_ACCEPTED,
                self::LEGACY_CONTACT_DECLINED,
                self::LEGACY_CONTACT_REVOKED,
                self::LEGACY_INVENTORY_CERT_ISSUED,
                self::SUCCESSION_CLAIM_SUBMITTED,
                self::SUCCESSION_CLAIM_APPROVED,
                self::SUCCESSION_CLAIM_REJECTED,
            ],
            'Transmissions C2C' => [
                self::TRANSFER_INITIATED,
                self::TRANSFER_ACCEPTED,
                self::TRANSFER_CANCELLED,
                self::TRANSFER_REJECTED,
                self::TRANSFER_EXPIRED,
            ],
            'Facturació & Pagaments' => [
                self::PAYMENT_COMPLETED,
                self::PAYMENT_FAILED,
                self::SUBSCRIPTION_STARTED,
                self::SUBSCRIPTION_CANCELLED,
                self::REFUND_PROCESSED,
            ],
        ];
    }
}
