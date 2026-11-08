<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\LegalGateSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LegalGateSubscriberTest extends TestCase
{
    private Security $security;
    private UrlGeneratorInterface $urlGenerator;
    private LegalGateSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->subscriber = new LegalGateSubscriber($this->security, $this->urlGenerator);
    }

    public function testIgnoresSubRequests(): void
    {
        $event = $this->createRequestEvent('/app/dashboard', 'app_dashboard', isMainRequest: false);
        $this->subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testIgnoresNonAppRoutes(): void
    {
        $event = $this->createRequestEvent('/producte', 'app_producte', isMainRequest: true);
        $this->subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsAccessToGateItself(): void
    {
        $event = $this->createRequestEvent('/app/legal/acceptar-condicions', LegalGateSubscriber::GATE_ROUTE, isMainRequest: true);
        $this->subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testAllowsAccessToLogout(): void
    {
        $event = $this->createRequestEvent('/app/logout', 'app_logout', isMainRequest: true);
        $this->subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsUnacceptedUserToLegalGate(): void
    {
        $user = new User();
        // user has null legalTermsAcceptedAt and null legalTermsVersion

        $this->security->method('getUser')->willReturn($user);
        $this->urlGenerator->method('generate')
            ->with(LegalGateSubscriber::GATE_ROUTE)
            ->willReturn('/app/legal/acceptar-condicions');

        $event = $this->createRequestEvent('/app/dashboard', 'app_dashboard', isMainRequest: true);
        $this->subscriber->onKernelRequest($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        self::assertSame('/app/legal/acceptar-condicions', $event->getResponse()->getTargetUrl());
    }

    public function testAllowsUserWithAcceptedCurrentVersion(): void
    {
        $user = new User();
        $user->setLegalTermsAcceptedAt(new \DateTimeImmutable());
        $user->setLegalTermsVersion(LegalGateSubscriber::CURRENT_TERMS_VERSION);

        $this->security->method('getUser')->willReturn($user);

        $event = $this->createRequestEvent('/app/dashboard', 'app_dashboard', isMainRequest: true);
        $this->subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testRedirectsUserWithOutdatedVersion(): void
    {
        $user = new User();
        $user->setLegalTermsAcceptedAt(new \DateTimeImmutable());
        $user->setLegalTermsVersion('v0.9-beta'); // Outdated version

        $this->security->method('getUser')->willReturn($user);
        $this->urlGenerator->method('generate')
            ->with(LegalGateSubscriber::GATE_ROUTE)
            ->willReturn('/app/legal/acceptar-condicions');

        $event = $this->createRequestEvent('/app/dashboard', 'app_dashboard', isMainRequest: true);
        $this->subscriber->onKernelRequest($event);

        self::assertInstanceOf(RedirectResponse::class, $event->getResponse());
        self::assertSame('/app/legal/acceptar-condicions', $event->getResponse()->getTargetUrl());
    }

    private function createRequestEvent(string $path, string $route, bool $isMainRequest): RequestEvent
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);

        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent(
            $kernel,
            $request,
            $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST
        );
    }
}
