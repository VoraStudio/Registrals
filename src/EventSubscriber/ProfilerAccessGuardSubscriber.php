<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Blocks external public access to Symfony WebProfiler / WDT endpoints
 * even when the dev server is temporarily reachable from outside localhost.
 */
final class ProfilerAccessGuardSubscriber implements EventSubscriberInterface
{
    private const ALLOWED_CIDRS = [
        '127.0.0.0/8',
        '::1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/_profiler') && !str_starts_with($path, '/_wdt')) {
            return;
        }

        $clientIp = $event->getRequest()->getClientIp() ?? '';
        if ($clientIp === '' || !IpUtils::checkIp($clientIp, self::ALLOWED_CIDRS)) {
            throw new NotFoundHttpException();
        }
    }
}
