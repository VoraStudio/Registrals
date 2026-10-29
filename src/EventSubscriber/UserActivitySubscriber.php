<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

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
}
