<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Security\Exception\PendingEmailVerificationException;
use App\Security\Exception\PendingTwoFactorAuthenticationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * Intercepta l'autenticació per contrasenya (form_login) just després que CheckCredentialsListener
 * hagi verificat la contrasenya, abans d'emetre cap token o galeta RememberMe,
 * per exigir la verificació de correu o el repte 2FA quan pertoqui.
 */
class EmailSecurityChallengeSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Prioritat -10 per executar-se sempre DESPRÉS de CheckCredentialsListener (prioritat 0)
        return [
            CheckPassportEvent::class => ['onCheckPassport', -10],
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();

        // Només aplica a l'inici de sessió interactiu per contrasenya (evita interferir amb Google OAuth, RememberMe o login programàtic post-OTP)
        if (!$passport->hasBadge(PasswordCredentials::class)) {
            return;
        }

        $user = $passport->getUser();
        if (!$user instanceof User || $user->getAuthProvider() !== 'local') {
            return;
        }

        if (!$user->isEmailVerified()) {
            throw new PendingEmailVerificationException($user);
        }

        if ($user->requiresLoginTwoFactor()) {
            throw new PendingTwoFactorAuthenticationException($user);
        }
    }
}
