<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ProfilerAccessGuardSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ProfilerAccessGuardSubscriberTest extends TestCase
{
    public function testBlocksExternalIpOnProfilerRoute(): void
    {
        $subscriber = new ProfilerAccessGuardSubscriber();
        $request = Request::create('/_profiler/phpinfo', 'GET', [], [], [], ['REMOTE_ADDR' => '148.251.71.4']);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testBlocksExternalIpOnWdtRoute(): void
    {
        $subscriber = new ProfilerAccessGuardSubscriber();
        $request = Request::create('/_wdt/cb8958', 'GET', [], [], [], ['REMOTE_ADDR' => '66.132.195.96']);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testAllowsLocalhostOnProfilerRoute(): void
    {
        $subscriber = new ProfilerAccessGuardSubscriber();
        $request = Request::create('/_profiler/phpinfo', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->addToAssertionCount(1);
    }

    public function testAllowsLanIpOnProfilerRoute(): void
    {
        $subscriber = new ProfilerAccessGuardSubscriber();
        $request = Request::create('/_profiler/search', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.45']);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->addToAssertionCount(1);
    }
}
