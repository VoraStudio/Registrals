<?php

declare(strict_types=1);

namespace App\Service\Security;

/**
 * Utilitat compartida per pseudonimitzar noms i correus electrònics en certificats
 * i consultes públiques (minimització de dades Art. 5.1.c i Art. 14 RGPD).
 */
final class PrivacyMasker
{
    public static function maskName(string $name, string $fallback = 'T****** A******'): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return $fallback;
        }

        $parts = preg_split('/\s+/', $trimmed);
        if ($parts === false || count($parts) === 0) {
            return 'T******';
        }

        $maskedParts = [];
        foreach ($parts as $part) {
            $length = mb_strlen($part);
            if ($length <= 1) {
                $maskedParts[] = $part . '***';
            } else {
                $firstChar = mb_substr($part, 0, 1);
                $maskedParts[] = $firstChar . str_repeat('*', min(6, $length - 1));
            }
        }

        return implode(' ', $maskedParts);
    }

    public static function maskEmail(string $email): string
    {
        $parts = explode('@', trim($email), 2);
        if (count($parts) !== 2) {
            return $email;
        }

        [$local, $domain] = $parts;
        $len = mb_strlen($local);
        if ($len <= 1) {
            return '*@' . $domain;
        }

        $visible = $len >= 4 ? mb_substr($local, 0, 2) : mb_substr($local, 0, 1);

        return $visible . str_repeat('*', max(2, $len - mb_strlen($visible))) . '@' . $domain;
    }
}

