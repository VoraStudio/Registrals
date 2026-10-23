<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class DeviceRecognitionSubscriber implements EventSubscriberInterface
{
    public const COOKIE_NAME = 'reg_device_user';

    private ?string $rememberUser = null;

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            KernelEvents::RESPONSE   => 'onKernelResponse',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $data = [
                'email'        => $user->getEmail(),
                'firstName'    => $user->getFirstName(),
                'authProvider' => $user->getAuthProvider(),
            ];
            $this->rememberUser = base64_encode((string) json_encode($data));
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->rememberUser !== null) {
            $response = $event->getResponse();
            $cookie = Cookie::create(
                self::COOKIE_NAME,
                $this->rememberUser,
                time() + (365 * 24 * 60 * 60), // 1 any
                '/',
                null,
                $event->getRequest()->isSecure(),
                true, // HttpOnly
                false,
                Cookie::SAMESITE_LAX
            );
            $response->headers->setCookie($cookie);
        }
    }
}
