<?php

declare(strict_types=1);

namespace App\Security\Exception;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * S'emet un cop validada la contrasenya si el compte local encara no ha completat la verificació de correu.
 */
class PendingEmailVerificationException extends AuthenticationException
{
    public function __construct(
        private readonly User $user,
        string $message = 'Cal verificar el teu correu electrònic abans d\'accedir.',
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
        return 'Cal verificar el teu correu electrònic amb el codi de 6 dígits abans d\'accedir.';
    }
}
