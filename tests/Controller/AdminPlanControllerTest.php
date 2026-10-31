<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\AdminPlanController;
use App\Entity\Plan;
use App\Repository\PlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class AdminPlanControllerTest extends TestCase
{
    private function createContainerWithServices(
        ?Environment $twig = null,
        ?UrlGeneratorInterface $router = null,
        ?FormFactoryInterface $formFactory = null,
        ?Session $session = null
    ): ContainerInterface {
        $container = $this->createMock(ContainerInterface::class);
        $services = [];

        if ($twig !== null) {
            $services['twig'] = $twig;
        }
        if ($router !== null) {
            $services['router'] = $router;
        }
        if ($formFactory !== null) {
            $services['form.factory'] = $formFactory;
        }
        if ($session !== null) {
            $requestStack = new RequestStack();
            $request = new Request();
            $request->setSession($session);
            $requestStack->push($request);
            $services['request_stack'] = $requestStack;
        }

        $container->method('has')->willReturnCallback(fn (string $id) => array_key_exists($id, $services));
        $container->method('get')->willReturnCallback(fn (string $id) => $services[$id] ?? null);

        return $container;
    }

    public function testIndexRendersPlanList(): void
    {
        $planFree = new Plan('free', 'Pla Free', 0);
        $planPlus = new Plan('plus', 'Pla Plus', 2200);

        $repo = $this->createMock(PlanRepository::class);
        $repo->expects($this->once())
            ->method('findAll')
            ->willReturn([$planFree, $planPlus]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('admin/plan/index.html.twig', $this->callback(function (array $params) use ($planFree, $planPlus): bool {
                $this->assertArrayHasKey('plans', $params);
                $this->assertCount(2, $params['plans']);
                $this->assertSame($planFree, $params['plans'][0]);
                $this->assertSame($planPlus, $params['plans'][1]);
                return true;
            }))
            ->willReturn('<html>Plans</html>');

        $controller = new AdminPlanController();
        $controller->setContainer($this->createContainerWithServices(twig: $twig));

        $response = $controller->index($repo);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('<html>Plans</html>', $response->getContent());
    }

    public function testToggleAiWithValidCsrfFlipsState(): void
    {
        $plan = new Plan('plus', 'Pla Plus', 2200);
        $plan->setAiAssistanceEnabled(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())
            ->method('isTokenValid')
            ->with($this->callback(function (CsrfToken $token): bool {
                return $token->getValue() === 'valid_token';
            }))
            ->willReturn(true);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_admin_plans')->willReturn('/admin/plans');

        $session = new Session(new MockArraySessionStorage());

        $controller = new AdminPlanController();
        $controller->setContainer($this->createContainerWithServices(
            router: $router,
            session: $session
        ));

        $request = new Request([], ['_csrf_token' => 'valid_token']);

        $response = $controller->toggleAi($plan, $request, $em, $csrf);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertFalse($plan->isAiAssistanceEnabled());
        $this->assertNotEmpty($session->getFlashBag()->get('success'));
    }

    public function testToggleAiWithInvalidCsrfRejects(): void
    {
        $plan = new Plan('plus', 'Pla Plus', 2200);
        $plan->setAiAssistanceEnabled(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->expects($this->once())
            ->method('isTokenValid')
            ->willReturn(false);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->with('app_admin_plans')->willReturn('/admin/plans');

        $session = new Session(new MockArraySessionStorage());

        $controller = new AdminPlanController();
        $controller->setContainer($this->createContainerWithServices(
            router: $router,
            session: $session
        ));

        $request = new Request([], ['_csrf_token' => 'bad_token']);

        $response = $controller->toggleAi($plan, $request, $em, $csrf);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($plan->isAiAssistanceEnabled());
        $this->assertNotEmpty($session->getFlashBag()->get('error'));
    }
}
