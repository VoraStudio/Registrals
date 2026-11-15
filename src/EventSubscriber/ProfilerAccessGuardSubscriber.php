<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
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
    private const LOCAL_LOOPBACK_CIDRS = [
        '127.0.0.0/8',
        '::1',
    ];

    private const PRIVATE_LAN_CIDRS = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    public function __construct(
        private readonly ?Profiler $profiler = null,
        private readonly ?string $extraAllowedIps = null,
        private readonly ?bool $forceDisabled = null,
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

        $isAllowed = !$this->isForceDisabled() && $this->isRequestAllowed($event->getRequest());

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

    private function isForceDisabled(): bool
    {
        if ($this->forceDisabled !== null) {
            return $this->forceDisabled;
        }

        $raw = $_ENV['PROFILER_DISABLED'] ?? $_SERVER['PROFILER_DISABLED'] ?? '0';

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    private function isRequestAllowed(Request $request): bool
    {
        $extraAllowed = $this->getExtraAllowedCidrs();

        // Any forwarded IP outside the explicit allowlist (spoofed or proxy-appended) is rejected.
        $forwardedFor = (string) $request->headers->get('X-Forwarded-For', '');
        if ($forwardedFor !== '') {
            $forwardedIps = array_values(array_filter(array_map('trim', explode(',', $forwardedFor))));
            if ($forwardedIps === []) {
                return false;
            }
            $allowedWithPrivate = array_merge(self::LOCAL_LOOPBACK_CIDRS, self::PRIVATE_LAN_CIDRS, $extraAllowed);
            foreach ($forwardedIps as $ip) {
                if (!IpUtils::checkIp($ip, $allowedWithPrivate)) {
                    return false;
                }
            }
        }

        $clientIp = $request->getClientIp() ?? '';
        if ($clientIp === '') {
            return false;
        }

        $host = strtolower($request->getHost());
        $isLocalHost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);

        // When accessed via a public host/IP (e.g. 83.43.195.95 or domain),
        // never trust loopback 127.0.0.1 because local reverse proxies (symfony server) use it.
        if (!$isLocalHost && IpUtils::checkIp($clientIp, self::LOCAL_LOOPBACK_CIDRS)) {
            return $forwardedFor !== '' && $extraAllowed !== [] && IpUtils::checkIp($forwardedIps[count($forwardedIps) - 1], $extraAllowed);
        }

        $allowed = array_merge(self::LOCAL_LOOPBACK_CIDRS, self::PRIVATE_LAN_CIDRS, $extraAllowed);

        return IpUtils::checkIp($clientIp, $allowed);
    }

    /**
     * @return list<string>
     */
    private function getExtraAllowedCidrs(): array
    {
        $raw = $this->extraAllowedIps
            ?? ($_ENV['PROFILER_ALLOWED_IPS'] ?? $_SERVER['PROFILER_ALLOWED_IPS'] ?? '');

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }
}
