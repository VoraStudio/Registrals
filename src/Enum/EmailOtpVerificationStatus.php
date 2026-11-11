<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Resultat de la comprovació d'un codi OTP de 6 dígits.
 */
enum EmailOtpVerificationStatus: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case EXPIRED_OR_MISSING = 'expired_or_missing';
    case MAX_ATTEMPTS_EXCEEDED = 'max_attempts_exceeded';
}
