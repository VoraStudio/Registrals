<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\ProfileController;
use App\Entity\Plan;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Twig\Environment;

class ProfileControllerTest extends TestCase
{
    public function testIndexRendersProfile(): void
    {
        $plan = new Plan('Plus', 'plus', 2200, 100, 5, 5, 100);
        $user = new User('test@registrals.com', 'hashed_pass', 'Carles', 'Test', $plan);
        $user->setStripeCustomerId('cus_test123');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('handleRequest')->willReturnSelf();
        $form->method('isSubmitted')->willReturn(false);
        $form->method('isValid')->willReturn(false);
        $form->method('createView')->willReturn($this->createMock(\Symfony\Component\Form\FormView::class));
        $formFactory->method('create')->willReturn($form);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('profile/index.html.twig', $this->callback(function (array $context) use ($user): bool {
                $this->assertSame($user, $context['user']);
                $this->assertArrayHasKey('profileForm', $context);
                $this->assertArrayHasKey('isOAuthUser', $context);
                return true;
            }))
            ->willReturn('<html>Profile rendered</html>');

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn(string $id) => in_array($id, ['security.token_storage', 'form.factory', 'twig'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $formFactory, $twig) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'form.factory' => $formFactory,
                'twig' => $twig,
                default => null,
            };
        });

        $controller = new ProfileController();
        $controller->setContainer($container);

        $request = new Request();
        $response = $controller->index($request, $entityManager, $passwordHasher);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<html>Profile rendered</html>', $response->getContent());
    }
}
