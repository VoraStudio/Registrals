<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Estat de tramitació d'un expedient post-mortem d'herència digital.
 */
enum SuccessionClaimStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case OWNER_NOTICE_WINDOW = 'owner_notice_window';
    case AI_PRESCREENING = 'ai_prescreening';
    case UNDER_HUMAN_REVIEW = 'under_human_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Pendent de taxa d\'obertura',
            self::OWNER_NOTICE_WINDOW => 'Finestra preventiva (72 h)',
            self::AI_PRESCREENING => 'Pre-triatge OCR en cua',
            self::UNDER_HUMAN_REVIEW => 'En revisió per gestor',
            self::APPROVED => 'Aprovat i traspassat',
            self::REJECTED => 'Rebutjat motivadament',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'bg-amber-500/15 text-amber-800 border-amber-500/30',
            self::OWNER_NOTICE_WINDOW => 'bg-orange-500/15 text-orange-800 border-orange-500/30',
            self::AI_PRESCREENING => 'bg-blue-500/15 text-blue-800 border-blue-500/30',
            self::UNDER_HUMAN_REVIEW => 'bg-indigo-500/15 text-indigo-800 border-indigo-500/30',
            self::APPROVED => 'bg-emerald-500/15 text-emerald-800 border-emerald-500/30',
            self::REJECTED => 'bg-rose-500/15 text-rose-800 border-rose-500/30',
        };
    }

    public function isResolved(): bool
    {
        return in_array($this, [self::APPROVED, self::REJECTED], true);
    }

    public function requiresHumanAction(): bool
    {
        return $this === self::UNDER_HUMAN_REVIEW;
    }
}

