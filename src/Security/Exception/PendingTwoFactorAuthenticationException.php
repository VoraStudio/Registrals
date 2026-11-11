<?php

declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * S'emet un cop validada la contrasenya si l'usuari té activat el segon factor per correu (2FA).
 */
class PendingTwoFactorAuthenticationException extends AuthenticationException
{
    public function __construct(
        private readonly User $user,
        string $message = 'Cal completar la verificació en dos passos (2FA).',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getMessageKey(): string
    {
        return 'Cal completar la verificació en dos passos (2FA) per iniciar sessió.';
    }
}
