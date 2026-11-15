<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ProfilerAccessGuardSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

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

    public function testDisablesProfilerForExternalIpOnNormalRoute(): void
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())->method('disable');

        $subscriber = new ProfilerAccessGuardSubscriber($profiler);
        $request = Request::create('/app/dashboard', 'GET', [], [], [], ['REMOTE_ADDR' => '83.43.195.95']);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    public function testAllowsConfiguredExtraIpOnProfilerRoute(): void
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->never())->method('disable');

        $subscriber = new ProfilerAccessGuardSubscriber($profiler, '83.43.195.95');
        $request = Request::create('/_profiler/phpinfo', 'GET', [], [], [], ['REMOTE_ADDR' => '83.43.195.95']);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->addToAssertionCount(1);
    }

    public function testBlocksLoopbackClientIpBehindProxyOnPublicHost(): void
    {
        $subscriber = new ProfilerAccessGuardSubscriber(null, '83.43.195.95');
        $request = Request::create('https://83.43.195.95:8008/_profiler/search', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testBlocksSpoofedXForwardedForWithBotIp(): void
    {
        $subscriber = new ProfilerAccessGuardSubscriber(null, '83.43.195.95');
        $request = Request::create('https://83.43.195.95:8008/_profiler/24bda2/search/results', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '127.0.0.1, 148.251.71.4',
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelRequest($event);
    }

    public function testBlocksEverythingWhenForceDisabled(): void
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())->method('disable');

        $subscriber = new ProfilerAccessGuardSubscriber($profiler, '83.43.195.95', true);
        $request = Request::create('https://127.0.0.1:8008/_profiler/phpinfo', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->expectException(NotFoundHttpException::class);
        $subscriber->onKernelRequest($event);
    }
}

