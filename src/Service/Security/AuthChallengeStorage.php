<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Enum\EmailOtpPurpose;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Gestiona l'estat en sessió del repte pendent de verificació per correu o 2FA
 * abans de completar l'autenticació al tallafoc.
 */
class AuthChallengeStorage
{
    public const SESSION_KEY = '_registrals_auth_challenge';
    public const CHALLENGE_TTL_SECONDS = 1800; // 30 minuts

    public function store(SessionInterface $session, int $userId, EmailOtpPurpose $purpose, bool $rememberMe = false): void
    {
        $session->set(self::SESSION_KEY, [
            'user_id' => $userId,
            'purpose' => $purpose->value,
            'remember_me' => $rememberMe,
            'created_at' => time(),
        ]);
    }

    /**
     * @return array{user_id: int, purpose: string, remember_me: bool, created_at: int}|null
     */
    public function get(SessionInterface $session, EmailOtpPurpose $expectedPurpose): ?array
    {
        $data = $session->get(self::SESSION_KEY);
        if (!is_array($data)) {
            return null;
        }

        $userId = $data['user_id'] ?? null;
        $purpose = $data['purpose'] ?? null;
        $createdAt = $data['created_at'] ?? null;

        if (!is_int($userId) || $userId <= 0 || $purpose !== $expectedPurpose->value || !is_int($createdAt)) {
            return null;
        }

        if ((time() - $createdAt) > self::CHALLENGE_TTL_SECONDS) {
            $this->clear($session);
            return null;
        }

        return [
            'user_id' => $userId,
            'purpose' => $purpose,
            'remember_me' => (bool) ($data['remember_me'] ?? false),
            'created_at' => $createdAt,
        ];
    }

    public function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }
}
