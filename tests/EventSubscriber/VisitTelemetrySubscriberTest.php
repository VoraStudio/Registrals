<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\SiteVisit;
use App\EventSubscriber\VisitTelemetrySubscriber;
use App\Service\GeoIp\GeoLocationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class VisitTelemetrySubscriberTest extends TestCase
{
    public function testRecordsPublicValidGetVisitWithHeaderCountry(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (SiteVisit $visit): bool {
                return $visit->getPath() === '/producte'
                    && $visit->getCountryCode() === 'US'
                    && $visit->getDeviceType() === 'mobile'
                    && strlen($visit->getIpHash()) === 64;
            }));
        $em->expects($this->once())->method('flush');

        $subscriber = new VisitTelemetrySubscriber($em, new GeoLocationService(), 'test-secret', 'prod');

        $request = Request::create('/producte', 'GET');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148');
        $request->headers->set('CF-IPCountry', 'US');

        $event = new TerminateEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            new Response('OK', 200)
        );

        $subscriber->onKernelTerminate($event);
    }

    public function testIgnoresAdmin404AndBotTraffic(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $subscriber = new VisitTelemetrySubscriber($em, new GeoLocationService(), 'test-secret', 'prod');
        $kernel = $this->createMock(HttpKernelInterface::class);

        // 1. /admin
        $adminReq = Request::create('/admin', 'GET');
        $adminReq->headers->set('User-Agent', 'Mozilla/5.0');
        $subscriber->onKernelTerminate(new TerminateEvent($kernel, $adminReq, new Response('OK', 200)));

        // 2. 404 scanner
        $scanReq = Request::create('/mcp/', 'GET');
        $scanReq->headers->set('User-Agent', 'Mozilla/5.0');
        $subscriber->onKernelTerminate(new TerminateEvent($kernel, $scanReq, new Response('Not Found', 404)));

        // 3. Bot
        $botReq = Request::create('/', 'GET');
        $botReq->headers->set('User-Agent', 'Googlebot/2.1');
        $subscriber->onKernelTerminate(new TerminateEvent($kernel, $botReq, new Response('OK', 200)));
    }
}

