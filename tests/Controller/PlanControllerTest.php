<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\PlanController;
use App\Entity\PaymentTransaction;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\PaymentStatus;
use App\Enum\PaymentType;
use App\Repository\ItemRepository;
use App\Repository\PaymentTransactionRepository;
use App\Repository\PlanRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

class PlanControllerTest extends TestCase
{
    public function testIndexRendersPlan(): void
    {
        $plan = new Plan('Plus', 'plus', 2200, 100, 5, 5, 100);
        $user = new User();
        $user->setEmail('test@registrals.com');
        $user->setFirstName('Carles');
        $user->setLastName('Test');
        $user->setPlan($plan);
        $user->setStripeCustomerId('cus_test123');

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findBy')->willReturn([$plan]);
        $planRepo->method('findByCode')->with('free')->willReturn($plan);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('countActiveByOwner')->with($user)->willReturn(5);
        $itemRepo->method('countFrozenByOwner')->with($user)->willReturn(0);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('plan/index.html.twig', $this->callback(function (array $context) use ($user, $plan): bool {
                $this->assertSame($user, $context['user']);
                $this->assertSame($plan, $context['currentPlan']);
                $this->assertArrayHasKey('allPlans', $context);
                $this->assertArrayHasKey('activeItemsCount', $context);
                $this->assertSame(5, $context['activeItemsCount']);
                $this->assertSame(0, $context['frozenCount']);
                $this->assertArrayHasKey('itemsPct', $context);
                return true;
            }))
            ->willReturn('<html>Plan rendered</html>');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => in_array($id, ['security.token_storage', 'twig'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $twig) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'twig' => $twig,
                default => null,
            };
        });

        $controller = new PlanController();
        $controller->setContainer($container);

        $response = $controller->index($planRepo, $itemRepo);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<html>Plan rendered</html>', $response->getContent());
    }

    public function testBillingRendersSubscriptionAndTransactions(): void
    {
        $plan = new Plan('Plus', 'plus', 2200, 100, 5, 5, 100);
        $user = new User();
        $user->setEmail('test@registrals.com');
        $user->setFirstName('Carles');
        $user->setLastName('Test');
        $user->setPlan($plan);
        $user->setStripeCustomerId('cus_test123');

        $transaction = new PaymentTransaction($user, PaymentType::SUBSCRIPTION, 2200);
        $transaction->setStatus(PaymentStatus::PAID);

        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findByCode')->willReturnCallback(function (string $code) use ($plan) {
            return match ($code) {
                'free', 'plus' => $plan,
                default => null,
            };
        });

        $transactionRepo = $this->createMock(PaymentTransactionRepository::class);
        $transactionRepo->expects($this->once())
            ->method('findByUser')
            ->with($user, 25)
            ->willReturn([$transaction]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('plan/billing.html.twig', $this->callback(function (array $context) use ($user, $plan, $transaction): bool {
                $this->assertSame($user, $context['user']);
                $this->assertSame($plan, $context['currentPlan']);
                $this->assertCount(1, $context['transactions']);
                $this->assertSame($transaction, $context['transactions'][0]);
                return true;
            }))
            ->willReturn('<html>Billing rendered</html>');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => in_array($id, ['security.token_storage', 'twig'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $twig) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'twig' => $twig,
                default => null,
            };
        });

        $controller = new PlanController();
        $controller->setContainer($container);

        $response = $controller->billing($planRepo, $transactionRepo);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<html>Billing rendered</html>', $response->getContent());
    }
}
