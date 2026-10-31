<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class UserActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UserActivityLogger $activityLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $this->activityLogger->log(
                user: $user,
                action: UserActivityAction::LOGIN,
                description: 'Inici de sessió correcte al sistema',
                details: [
                    'firewall' => $event->getFirewallName(),
                    'provider' => $user->getAuthProvider(),
                ]
            );
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $passport = $event->getPassport();
        $identifier = 'desconegut';
        if ($passport && $passport->hasBadge(UserBadge::class)) {
            $identifier = $passport->getBadge(UserBadge::class)->getUserIdentifier();
        }

        $this->activityLogger->logAnonymous(
            identifier: $identifier,
            action: UserActivityAction::LOGIN_FAILED,
            description: sprintf('Intent fallit d\'inici de sessió per a "%s"', $identifier),
            details: [
                'firewall' => $event->getFirewallName(),
                'error' => $event->getException()->getMessageKey(),
            ]
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        if ($user instanceof User) {
            $this->activityLogger->log(
                user: $user,
                action: UserActivityAction::LOGOUT,
                description: 'Tancament de sessió d\'usuari',
                details: []
            );
        }
    }
}
