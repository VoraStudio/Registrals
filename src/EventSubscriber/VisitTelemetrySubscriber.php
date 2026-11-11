<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\SiteVisit;
use App\Service\GeoIp\GeoLocationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Telemetria web passiva i sense galetes (RGPD per Disseny) a KernelEvents::TERMINATE.
 * Registra visites públiques vàlides amb país resolt localment per alimentar el mapa geogràfic.
 */
class VisitTelemetrySubscriber implements EventSubscriberInterface
{
    private const IGNORED_EXTENSIONS = [
        'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp',
        'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'json', 'xml', 'txt', 'pdf',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GeoLocationService $geoLocationService,
        private readonly string $appSecret,
        private readonly string $environment = 'prod',
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => ['onKernelTerminate', 0],
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->environment === 'test' || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        // Evitem contaminar el mapa amb rutes d'escàners 404/403 (ex. /mcp, /.env, /wp-login)
        if ($event->getResponse()->getStatusCode() >= 400) {
            return;
        }

        $path = $request->getPathInfo();
        if ($this->shouldIgnorePath($path)) {
            return;
        }

        $userAgent = (string) $request->headers->get('User-Agent', '');
        if ($userAgent === '' || preg_match('/(bot|crawl|spider|slurp|curl|wget|python|headless|uptime|monitor|facebookexternalhit|whatsapp|go-http-client)/i', $userAgent)) {
            return;
        }

        $clientIp = $request->getClientIp() ?: '127.0.0.1';
        $cfCountry = $request->headers->get('CF-IPCountry') ?: $request->server->get('HTTP_CF_IPCOUNTRY');
        $cfCountryString = is_string($cfCountry) ? $cfCountry : null;
        $geo = $this->geoLocationService->resolveCountry($clientIp, $cfCountryString);

        $deviceType = 'desktop';
        if (preg_match('/(mobile|android|iphone|ipod|blackberry|opera mini)/i', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/(ipad|tablet)/i', $userAgent)) {
            $deviceType = 'tablet';
        }

        $ipHash = hash_hmac('sha256', $clientIp . '|' . date('Y-m-d'), $this->appSecret);

        try {
            if (!$this->entityManager->isOpen()) {
                return;
            }

            $visit = new SiteVisit($path, $geo['code'], $ipHash, $deviceType);
            $this->entityManager->persist($visit);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger?->warning('Error enregistrant telemetria de visita: ' . $e->getMessage());
        }
    }

    private function shouldIgnorePath(string $path): bool
    {
        foreach (['/admin', '/app', '/api', '/_', '/bundles', '/connect', '/webhook'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::IGNORED_EXTENSIONS, true)) {
            return true;
        }

        return false;
    }
}
