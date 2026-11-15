<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Profiler\Profiler;

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

    public function __construct(
        private readonly ?Profiler $profiler = null,
    ) {
    }

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

        $clientIp = $event->getRequest()->getClientIp() ?? '';
        $isAllowed = $clientIp !== '' && IpUtils::checkIp($clientIp, self::ALLOWED_CIDRS);

        if (!$isAllowed) {
            $this->profiler?->disable();
        }

        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/_profiler') && !str_starts_with($path, '/_wdt')) {
            return;
        }

        if (!$isAllowed) {
            throw new NotFoundHttpException();
        }
    }
}

