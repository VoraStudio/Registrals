<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            $reason = $user->getBlockReason() ? ' Motiu: ' . $user->getBlockReason() : '';
            throw new CustomUserMessageAccountStatusException(
                'El teu compte d\'usuari ha estat suspès o desactivat per l\'administració.' . $reason
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // No checks needed post-auth
    }
}
