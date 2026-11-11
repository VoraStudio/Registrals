<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PaymentController;
use App\Entity\Item;
use App\Entity\PaymentTransaction;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Infrastructure\Adapter\Payment\PaymentGatewayInterface;
use App\Repository\ItemRepository;
use App\Repository\ItemTransferRepository;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use App\Service\Payment\PaymentManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class PaymentControllerTest extends TestCase
{
    private function createContainerWithServices(
        User $user,
        ?Environment $twig = null,
        ?UrlGeneratorInterface $router = null,
        ?Session $session = null,
        bool $csrfValid = true
    ): ContainerInterface {
        $container = $this->createMock(ContainerInterface::class);
        $services = [];

        if ($twig !== null) {
            $services['twig'] = $twig;
        }

        if ($router !== null) {
            $services['router'] = $router;
        }

        if ($session !== null) {
            $requestStack = new RequestStack();
            $request = new Request();
            $request->setSession($session);
            $requestStack->push($request);
            $services['request_stack'] = $requestStack;
        }

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($csrfValid);
        $services['security.csrf.token_manager'] = $csrfTokenManager;

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->method('getToken')->willReturn($token);
        $services['security.token_storage'] = $tokenStorage;

        $container->method('has')->willReturnCallback(fn (string $id) => array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(fn (string $id) => $services[$id] ?? null);

        return $container;
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('client@registrals.com');
        $user->setFirstName('Carles');
        $user->setLastName('Vora');
        return $user;
    }

    public function testCheckoutPlanRejectsInvalidCsrf(): void
    {
        $user = $this->createUser();
        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/app/plan');

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentManager = $this->createMock(PaymentManager::class);
        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $planRepo = $this->createMock(PlanRepository::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);

        $controller = new PaymentController(
            $paymentGateway,
            $paymentManager,
            $transactionRepo,
            $planRepo,
            $itemRepo,
            $transferRepo
        );

        $controller->setContainer($this->createContainerWithServices(
            $user,
            router: $router,
            session: $session,
            csrfValid: false
        ));

        $request = new Request([], ['_token' => 'invalid_csrf']);
        $request->setSession($session);

        $response = $controller->checkoutPlan('plus', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app/plan', $response->getTargetUrl());
        $this->assertNotEmpty($session->getFlashBag()->get('error'));
    }

    public function testCheckoutPlanWithValidCsrfRedirectsToStripe(): void
    {
        $user = $this->createUser();
        $plan = new Plan('plus', 'Pla Plus', 2200);

        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(function (string $route) {
            return match ($route) {
                'app_payment_success' => 'https://registrals.com/app/payment/success',
                'app_plan' => 'https://registrals.com/app/plan',
                default => '/app',
            };
        });

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createSubscriptionCheckout')
            ->willReturn([
                'sessionId' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            ]);

        $paymentManager = $this->createMock(PaymentManager::class);
        $paymentManager->expects($this->once())
            ->method('createPendingTransaction');

        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findByCode')->with('plus')->willReturn($plan);

        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);

        $controller = new PaymentController(
            $paymentGateway,
            $paymentManager,
            $transactionRepo,
            $planRepo,
            $itemRepo,
            $transferRepo
        );

        $controller->setContainer($this->createContainerWithServices(
            $user,
            router: $router,
            session: $session,
            csrfValid: true
        ));

        $request = new Request([], ['_token' => 'valid_csrf']);
        $request->setSession($session);

        $response = $controller->checkoutPlan('plus', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://checkout.stripe.com/pay/cs_test_123', $response->getTargetUrl());
    }

    public function testCheckoutCertificateWithValidCsrfRedirectsToStripe(): void
    {
        $user = $this->createUser();
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($user, 10);

        $category = new \App\Entity\Category('rellotges', 'Rellotges');
        $item = new Item($user, 'REG-2026-82KQM4', 'Rellotge Rolex Submariner', $category);

        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(function (string $route) {
            return match ($route) {
                'app_payment_success' => 'https://registrals.com/app/payment/success',
                'app_item_show' => 'https://registrals.com/app/items/REG-2026-82KQM4',
                default => '/app',
            };
        });

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createCertificateCheckout')
            ->willReturn([
                'sessionId' => 'cs_cert_123',
                'url' => 'https://checkout.stripe.com/pay/cs_cert_123',
            ]);

        $paymentManager = $this->createMock(PaymentManager::class);
        $paymentManager->expects($this->once())
            ->method('createPendingTransaction');

        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $planRepo = $this->createMock(PlanRepository::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->expects($this->once())
            ->method('findOneByCui')
            ->with('REG-2026-82KQM4')
            ->willReturn($item);

        $transferRepo = $this->createMock(ItemTransferRepository::class);

        $controller = new PaymentController(
            $paymentGateway,
            $paymentManager,
            $transactionRepo,
            $planRepo,
            $itemRepo,
            $transferRepo
        );

        $controller->setContainer($this->createContainerWithServices(
            $user,
            router: $router,
            session: $session,
            csrfValid: true
        ));

        $request = new Request([], ['_token' => 'valid_csrf']);
        $request->setSession($session);

        $response = $controller->checkoutCertificate('REG-2026-82KQM4', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://checkout.stripe.com/pay/cs_cert_123', $response->getTargetUrl());
    }

    public function testCustomerPortalRedirectsToPortalUrl(): void
    {
        $user = $this->createUser();
        $user->setStripeCustomerId('cus_12345');

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://registrals.com/app/plan');

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createCustomerPortalSession')
            ->willReturn('https://billing.stripe.com/p/session/portal_123');

        $paymentManager = $this->createMock(PaymentManager::class);
        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $planRepo = $this->createMock(PlanRepository::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);

        $controller = new PaymentController(
            $paymentGateway,
            $paymentManager,
            $transactionRepo,
            $planRepo,
            $itemRepo,
            $transferRepo
        );

        $controller->setContainer($this->createContainerWithServices($user, router: $router));

        $response = $controller->customerPortal();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://billing.stripe.com/p/session/portal_123', $response->getTargetUrl());
    }

    public function testPaymentStatusReturnsJsonResponse(): void
    {
        $user = $this->createUser();
        $transaction = new PaymentTransaction($user, PaymentType::SUBSCRIPTION, 2200);
        $transaction->setStatus(PaymentStatus::PAID);

        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $transactionRepo->method('findByStripeSessionId')->with('cs_123')->willReturn($transaction);

        $controller = new PaymentController(
            $this->createMock(PaymentGatewayInterface::class),
            $this->createMock(PaymentManager::class),
            $transactionRepo,
            $this->createMock(PlanRepository::class),
            $this->createMock(ItemRepository::class),
            $this->createMock(ItemTransferRepository::class)
        );

        $response = $controller->status('cs_123');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $content = json_decode((string) $response->getContent(), true);
        $this->assertSame('paid', $content['status']);
        $this->assertTrue($content['paid']);
    }

    public function testCheckoutPlanRejectsInactiveOrUnlimitedPlan(): void
    {
        $user = $this->createUser();
        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/app/plan');

        $unlimitedPlan = new Plan('unlimited', 'Pla Unlimited', 5900);
        $unlimitedPlan->setIsActive(false);

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentManager = $this->createMock(PaymentManager::class);
        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findByCode')->with('unlimited')->willReturn($unlimitedPlan);
        $itemRepo = $this->createMock(ItemRepository::class);
        $transferRepo = $this->createMock(ItemTransferRepository::class);

        $controller = new PaymentController(
            $paymentGateway,
            $paymentManager,
            $transactionRepo,
            $planRepo,
            $itemRepo,
            $transferRepo
        );

        $controller->setContainer($this->createContainerWithServices(
            $user,
            router: $router,
            session: $session,
            csrfValid: true
        ));

        $request = new Request([], ['_token' => 'valid_csrf']);
        $request->setSession($session);

        $response = $controller->checkoutPlan('unlimited', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app/plan', $response->getTargetUrl());
        $warningFlashes = $session->getFlashBag()->get('warning');
        $this->assertNotEmpty($warningFlashes);
        $this->assertStringContainsString('Pla Unlimited estarà disponible properament', $warningFlashes[0]);
    }

    public function testCancelSubscriptionRejectsInvalidCsrf(): void
    {
        $user = $this->createUser();
        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_plan_billing')->willReturn('/app/perfil/facturacio');

        $controller = new PaymentController(
            $this->createMock(PaymentGatewayInterface::class),
            $this->createMock(PaymentManager::class),
            $this->createMock(PaymentTransactionRepository::class),
            $this->createMock(PlanRepository::class),
            $this->createMock(ItemRepository::class),
            $this->createMock(ItemTransferRepository::class)
        );

        $controller->setContainer($this->createContainerWithServices(
            $user,
            router: $router,
            session: $session,
            csrfValid: false
        ));

        $request = new Request([], ['_token' => 'invalid_csrf']);
        $request->setSession($session);

        $response = $controller->cancelSubscription($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app/perfil/facturacio', $response->getTargetUrl());
        $this->assertNotEmpty($session->getFlashBag()->get('error'));
    }

    public function testCancelSubscriptionSuccess(): void
    {
        $user = $this->createUser();
        $plusPlan = new Plan('plus', 'Pla Plus', 2200);
        $user->setPlan($plusPlan);
        $user->setSubscriptionStatus('active');

        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_plan_billing')->willReturn('/app/perfil/facturacio');

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentManager = $this->createMock(PaymentManager::class);
        $paymentManager->expects($this->once())
            ->method('cancelUserSubscription')
            ->with($user, $gateway)
            ->willReturn(true);

        $controller = new PaymentController(
            $gateway,
            $paymentManager,
            $this->createMock(PaymentTransactionRepository::class),
            $this->createMock(PlanRepository::class),
            $this->createMock(ItemRepository::class),
            $this->createMock(ItemTransferRepository::class)
        );

        $controller->setContainer($this->createContainerWithServices(
            $user,
            router: $router,
            session: $session,
            csrfValid: true
        ));

        $request = new Request([], ['_token' => 'valid_csrf']);
        $request->setSession($session);

        $response = $controller->cancelSubscription($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/app/perfil/facturacio', $response->getTargetUrl());
        $this->assertNotEmpty($session->getFlashBag()->get('success'));
    }

    public function testCheckoutCertificateUsesPlanExtraCertificatePrice(): void
    {
        $freePlan = new Plan('free', 'Pla Free', 0);
        $freePlan->setExtraCertificatePriceCents(650);

        $user = $this->createUser();
        $user->setPlan($freePlan);
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($user, 10);

        $category = new \App\Entity\Category('joies', 'Joies');
        $item = new Item($user, 'REG-2026-CERT01', 'Anell Or', $category);
        $refItem = new \ReflectionProperty(Item::class, 'id');
        $refItem->setValue($item, 42);

        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://registrals.com/app/return');

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createCertificateCheckout')
            ->with($user, $item, 650, $this->anything(), $this->anything())
            ->willReturn([
                'sessionId' => 'cs_cert_650',
                'url' => 'https://checkout.stripe.com/pay/cs_cert_650',
            ]);

        $paymentManager = $this->createMock(PaymentManager::class);
        $paymentManager->expects($this->once())
            ->method('createPendingTransaction')
            ->with($user, PaymentType::CERTIFICATE, 650, 'cs_cert_650', $this->anything());

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneByCui')->with('REG-2026-CERT01')->willReturn($item);

        $controller = new PaymentController(
            $paymentGateway,
            $paymentManager,
            $this->createMock(PaymentTransactionRepository::class),
            $this->createMock(PlanRepository::class),
            $itemRepo,
            $this->createMock(ItemTransferRepository::class)
        );

        $controller->setContainer($this->createContainerWithServices(
            $user,
            router: $router,
            session: $session,
            csrfValid: true
        ));

        $request = new Request([], ['_token' => 'valid_csrf']);
        $request->setSession($session);

        $response = $controller->checkoutCertificate('REG-2026-CERT01', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://checkout.stripe.com/pay/cs_cert_650', $response->getTargetUrl());
    }

    public function testCheckoutTransferUsesTransferFeeCentsInsteadOfHardcodedAmount(): void
    {
        $sender = $this->createUser();
        $recipient = $this->createUser();
        $refRecipient = new \ReflectionProperty(User::class, 'id');
        $refRecipient->setValue($recipient, 22);

        $category = new \App\Entity\Category('rellotges', 'Rellotges');
        $item = new Item($sender, 'REG-2026-TXF001', 'Omega Speedmaster', $category);
        $transfer = new \App\Entity\ItemTransfer($item, $sender, 'buyer@example.com', 340);
        $refTransfer = new \ReflectionProperty(\App\Entity\ItemTransfer::class, 'id');
        $refTransfer->setValue($transfer, 77);

        $session = new Session(new MockArraySessionStorage());
        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('https://registrals.com/app/return');

        $paymentGateway = $this->createMock(PaymentGatewayInterface::class);
        $paymentGateway->expects($this->once())
            ->method('createTransferCheckout')
            ->with($recipient, $transfer, 340, $this->anything(), $this->anything())
            ->willReturn([
                'sessionId' => 'cs_txf_340',
                'url' => 'https://checkout.stripe.com/pay/cs_txf_340',
            ]);

        $paymentManager = $this->createMock(PaymentManager::class);
        $paymentManager->expects($this->once())
            ->method('createPendingTransaction')
            ->with($recipient, PaymentType::TRANSFER, 340, 'cs_txf_340', $this->anything());

        $transferRepo = $this->createMock(ItemTransferRepository::class);
        $transferRepo->method('findByToken')->with('tok_custom_340')->willReturn($transfer);

        $controller = new PaymentController(
            $paymentGateway,
            $paymentManager,
            $this->createMock(PaymentTransactionRepository::class),
            $this->createMock(PlanRepository::class),
            $this->createMock(ItemRepository::class),
            $transferRepo
        );

        $controller->setContainer($this->createContainerWithServices(
            $recipient,
            router: $router,
            session: $session,
            csrfValid: true
        ));

        $request = new Request([], ['_token' => 'valid_csrf']);
        $request->setSession($session);

        $response = $controller->checkoutTransfer('tok_custom_340', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://checkout.stripe.com/pay/cs_txf_340', $response->getTargetUrl());
    }
}
