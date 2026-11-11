<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\Admin\AdminSuccessionClaimController;
use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Repository\ItemRepository;
use App\Repository\SuccessionClaimRepository;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class AdminSuccessionClaimControllerTest extends TestCase
{
    private function createClaim(): SuccessionClaim
    {
        $owner = (new User())->setEmail('anna@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $contact = (new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat'))
            ->accept((new User())->setEmail('maria@exemple.cat')->setFirstName('Maria')->setLastName('Puig'));
        $cert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000980',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT.pdf',
            pdfSha256: str_repeat('a', 64),
            itemFingerprintAtIssue: str_repeat('b', 64),
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
        );

        return new SuccessionClaim(
            'LEG-2026-000100',
            $cert,
            $owner,
            'Maria Puig',
            'maria@exemple.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000100/defuncio.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000100/titol.jpg',
            str_repeat('2', 64),
        );
    }

    private function createContainer(?Environment $twig = null, ?Request $request = null, bool $csrfValid = true): ContainerInterface
    {
        $actor = (new User())->setEmail('gestor@registrals.cat')->setFirstName('Gestor')->setLastName('Custòdia')->setRoles(['ROLE_GESTOR']);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(new UsernamePasswordToken($actor, 'main', $actor->getRoles()));

        $csrf = $this->createMock(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturn($csrfValid);

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/gestions/herencies/detail');

        $session = new Session(new MockArraySessionStorage());
        $req = $request ?? new Request();
        $req->setSession($session);
        $stack = new RequestStack();
        $stack->push($req);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => in_array($id, [
            'security.token_storage',
            'security.csrf.token_manager',
            'router',
            'request_stack',
            'twig',
        ], true) && ($id !== 'twig' || $twig !== null));
        $container->method('get')->willReturnCallback(fn (string $id) => match ($id) {
            'security.token_storage' => $tokenStorage,
            'security.csrf.token_manager' => $csrf,
            'router' => $router,
            'request_stack' => $stack,
            'twig' => $twig,
            default => null,
        });

        return $container;
    }

    public function testIndexRendersQueueWithStatusCounts(): void
    {
        $claim = $this->createClaim();
        $repo = $this->createMock(SuccessionClaimRepository::class);
        $repo->method('findFilteredQueue')->willReturn([$claim]);
        $repo->method('findQueueOrdered')->willReturn([$claim]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('admin/succession/index.html.twig', $this->callback(function (array $params) use ($claim): bool {
                return count($params['claims']) === 1
                    && $params['claims'][0] === $claim
                    && $params['counts']['total'] === 1;
            }))
            ->willReturn('<html>queue</html>');

        $controller = new AdminSuccessionClaimController(
            $repo,
            $this->createMock(ItemRepository::class),
            $this->createMock(SuccessionClaimWorkflowService::class),
            $this->createMock(FilesystemOperator::class),
        );
        $controller->setContainer($this->createContainer($twig));

        $response = $controller->index(new Request());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testStreamDocumentReturnsStreamedResponseWithInlineContentDisposition(): void
    {
        $claim = $this->createClaim();
        $repo = $this->createMock(SuccessionClaimRepository::class);
        $repo->method('findByUuid')->willReturn($claim);

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->method('fileExists')->willReturn(true);

        $controller = new AdminSuccessionClaimController(
            $repo,
            $this->createMock(ItemRepository::class),
            $this->createMock(SuccessionClaimWorkflowService::class),
            $storage,
        );
        $controller->setContainer($this->createContainer());

        $response = $controller->streamDocument($claim->getUuid()->toRfc4122(), 'defuncio');

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline; filename="LEG-2026-000100_defuncio.pdf"', (string) $response->headers->get('Content-Disposition'));
    }

    public function testApproveDelegatesToWorkflowAndRedirects(): void
    {
        $claim = $this->createClaim();
        $uuid = $claim->getUuid()->toRfc4122();
        $repo = $this->createMock(SuccessionClaimRepository::class);
        $repo->method('findByUuid')->willReturn($claim);

        $workflow = $this->createMock(SuccessionClaimWorkflowService::class);
        $workflow->expects($this->once())
            ->method('approveClaim')
            ->with($claim, $this->anything(), 'Documentació verificada.')
            ->willReturn(4);

        $request = new Request([], [
            '_token' => 'ok',
            'resolution_notes' => 'Documentació verificada.',
        ]);

        $controller = new AdminSuccessionClaimController(
            $repo,
            $this->createMock(ItemRepository::class),
            $workflow,
            $this->createMock(FilesystemOperator::class),
        );
        $controller->setContainer($this->createContainer(null, $request, true));

        $response = $controller->approve($uuid, $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}

