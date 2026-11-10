<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\UserCertificateController;
use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Service\Certificate\CertificateIssuerService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class UserCertificateControllerTest extends TestCase
{
    private function createContainerWithUser(
        User $user,
        ?Environment $twig = null,
        bool $csrfValid = true,
        bool $isGranted = true,
        ?Request $request = null
    ): ContainerInterface {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn($isGranted);

        $csrfManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfManager->method('isTokenValid')->willReturn($csrfValid);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(fn (string $name, array $params = []) => '/' . $name);

        $session = new Session(new MockArraySessionStorage());
        $requestStack = new RequestStack();
        $targetRequest = $request ?? new Request();
        $targetRequest->setSession($session);
        $requestStack->push($targetRequest);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => in_array($id, [
            'security.token_storage',
            'security.authorization_checker',
            'security.csrf.token_manager',
            'router',
            'request_stack',
            'twig',
        ], true) && ($id !== 'twig' || $twig !== null));

        $container->method('get')->willReturnCallback(function (string $id) use (
            $tokenStorage,
            $authChecker,
            $csrfManager,
            $urlGenerator,
            $requestStack,
            $twig
        ) {
            return match ($id) {
                'security.token_storage' => $tokenStorage,
                'security.authorization_checker' => $authChecker,
                'security.csrf.token_manager' => $csrfManager,
                'router' => $urlGenerator,
                'request_stack' => $requestStack,
                'twig' => $twig,
                default => null,
            };
        });

        return $container;
    }

    public function testIndexRendersCertificatesAndMetrics(): void
    {
        $user = new User('carles@registrals.com', 'Carles', 'Vora');
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($user, 10);

        $certRepo = $this->createMock(CertificateRepository::class);
        $flysystem = $this->createMock(FilesystemOperator::class);

        $category = new Category('Joieria', 'joieria');
        $item = new Item($user, 'REG-2026-TEST', 'Anell d\'Or', $category);

        $cert = new Certificate(
            item: $item,
            issuedBy: $user,
            certificateNumber: 'CERT-2026-000001',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/test.pdf',
            pdfSha256: hash('sha256', 'dummy'),
            itemFingerprintAtIssue: hash('sha256', 'fingerprint')
        );

        $certRepo->expects($this->once())
            ->method('findForUser')
            ->with($user, 'anell', 'official', 'valid')
            ->willReturn([$cert]);

        $certRepo->expects($this->once())
            ->method('countMetricsForUser')
            ->with($user)
            ->willReturn([
                'total' => 1,
                'official' => 1,
                'draft' => 0,
                'valid' => 1,
            ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $template, array $params) use ($cert) {
                $this->assertSame('certificate/index.html.twig', $template);
                $this->assertCount(1, $params['certificates']);
                $this->assertSame($cert, $params['certificates'][0]);
                $this->assertSame(1, $params['metrics']['total']);
                return '<html>rendered</html>';
            });

        $issuer = $this->createMock(CertificateIssuerService::class);
        $controller = new UserCertificateController($certRepo, $flysystem, $issuer);
        $controller->setContainer($this->createContainerWithUser($user, $twig));

        $request = new Request([
            'q' => 'anell',
            'mode' => 'official',
            'status' => 'valid',
        ]);

        $response = $controller->index($request);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testDownloadThrowsAccessDeniedWhenUserNotAuthorized(): void
    {
        $owner = new User('albert@registrals.com', 'Albert', 'Vora');
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($owner, 10);

        $otherUser = new User('intruder@registrals.com', 'Intruder', 'Evil');
        $refUser->setValue($otherUser, 99);

        $category = new Category('Joieria', 'joieria');
        $item = new Item($owner, 'REG-2026-TEST', 'Anell d\'Or', $category);

        $cert = new Certificate(
            item: $item,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000001',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/test.pdf',
            pdfSha256: hash('sha256', 'dummy'),
            itemFingerprintAtIssue: hash('sha256', 'fingerprint')
        );

        $certRepo = $this->createMock(CertificateRepository::class);
        $flysystem = $this->createMock(FilesystemOperator::class);

        $issuer = $this->createMock(CertificateIssuerService::class);
        $controller = new UserCertificateController($certRepo, $flysystem, $issuer);
        $controller->setContainer($this->createContainerWithUser($otherUser));

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $controller->download($cert);
    }

    public function testDownloadStreamsPdfSuccessfullyWhenAuthorized(): void
    {
        $owner = new User('albert@registrals.com', 'Albert', 'Vora');
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($owner, 10);

        $category = new Category('Joieria', 'joieria');
        $item = new Item($owner, 'REG-2026-TEST', 'Anell d\'Or', $category);

        $cert = new Certificate(
            item: $item,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000001',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/test.pdf',
            pdfSha256: hash('sha256', 'dummy'),
            itemFingerprintAtIssue: hash('sha256', 'fingerprint')
        );

        $certRepo = $this->createMock(CertificateRepository::class);
        $flysystem = $this->createMock(FilesystemOperator::class);
        $flysystem->method('fileExists')->with('certificates/test.pdf')->willReturn(true);
        $flysystem->method('read')->with('certificates/test.pdf')->willReturn('%PDF-1.4 test binary');

        $issuer = $this->createMock(CertificateIssuerService::class);
        $controller = new UserCertificateController($certRepo, $flysystem, $issuer);
        $controller->setContainer($this->createContainerWithUser($owner));

        $response = $controller->download($cert);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline; filename="REG-2026-TEST_CERT-2026-000001.pdf"', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 test binary', $response->getContent());
    }

    public function testDownloadByUuidStringStreamsPdfSuccessfully(): void
    {
        $owner = new User('albert@registrals.com', 'Albert', 'Vora');
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($owner, 10);

        $category = new Category('Joieria', 'joieria');
        $item = new Item($owner, 'REG-2026-TEST', 'Anell d\'Or', $category);

        $cert = new Certificate(
            item: $item,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000001',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/test.pdf',
            pdfSha256: hash('sha256', 'dummy'),
            itemFingerprintAtIssue: hash('sha256', 'fingerprint')
        );

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('findByUuid')->with($cert->getUuid()->toRfc4122())->willReturn($cert);

        $flysystem = $this->createMock(FilesystemOperator::class);
        $flysystem->method('fileExists')->with('certificates/test.pdf')->willReturn(true);
        $flysystem->method('read')->with('certificates/test.pdf')->willReturn('%PDF-1.4 test binary');

        $issuer = $this->createMock(CertificateIssuerService::class);
        $controller = new UserCertificateController($certRepo, $flysystem, $issuer);
        $controller->setContainer($this->createContainerWithUser($owner));

        $response = $controller->download($cert->getUuid()->toRfc4122());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('%PDF-1.4 test binary', $response->getContent());
    }

    public function testBatchIssueDraftCertificatesSuccessfully(): void
    {
        $owner = new User('albert@registrals.com', 'Albert', 'Vora');
        $refUser = new \ReflectionProperty(User::class, 'id');
        $refUser->setValue($owner, 10);

        $category = new Category('Joieria', 'joieria');
        $item1 = new Item($owner, 'REG-2026-B01', 'Rellotge 1', $category);
        $item2 = new Item($owner, 'REG-2026-B02', 'Rellotge 2', $category);

        $cert1 = new Certificate($item1, $owner, 'CERT-2026-000101', CertificateMode::DRAFT, 'p1.pdf', 'h1', 'f1');
        $cert2 = new Certificate($item2, $owner, 'CERT-2026-000102', CertificateMode::DRAFT, 'p2.pdf', 'h2', 'f2');

        $certRepo = $this->createMock(CertificateRepository::class);
        $flysystem = $this->createMock(FilesystemOperator::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneBy')->willReturnCallback(fn (array $c) => match ($c['cui'] ?? null) {
            'REG-2026-B01' => $item1,
            'REG-2026-B02' => $item2,
            default => null,
        });

        $issuer = $this->createMock(CertificateIssuerService::class);
        $issuer->expects($this->exactly(2))
            ->method('issueCertificate')
            ->willReturnOnConsecutiveCalls($cert1, $cert2);

        $controller = new UserCertificateController($certRepo, $flysystem, $issuer, null, $itemRepo);
        $request = new Request([], [
            '_token' => 'valid',
            'cuis' => ['REG-2026-B01', 'REG-2026-B02'],
            'mode' => CertificateMode::DRAFT->value,
        ]);
        $controller->setContainer($this->createContainerWithUser($owner, null, true, true, $request));

        $response = $controller->batchIssue($request);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/app_certificates_index', $response->headers->get('Location'));
    }

    public function testBatchIssueFailsWithInvalidCsrfToken(): void
    {
        $owner = new User('albert@registrals.com', 'Albert', 'Vora');
        $certRepo = $this->createMock(CertificateRepository::class);
        $flysystem = $this->createMock(FilesystemOperator::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $issuer = $this->createMock(CertificateIssuerService::class);
        $issuer->expects($this->never())->method('issueCertificate');

        $controller = new UserCertificateController($certRepo, $flysystem, $issuer, null, $itemRepo);
        $request = new Request([], [
            '_token' => 'bad',
            'cuis' => ['REG-2026-B01'],
        ]);
        $controller->setContainer($this->createContainerWithUser($owner, null, false, true, $request));

        $response = $controller->batchIssue($request);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/app_dashboard', $response->headers->get('Location'));
    }

    public function testBatchIssueStopsCleanlyOnPlanOrQuotaRuntimeException(): void
    {
        $owner = new User('albert@registrals.com', 'Albert', 'Vora');
        $category = new Category('Joieria', 'joieria');
        $item1 = new Item($owner, 'REG-2026-B01', 'Rellotge 1', $category);

        $certRepo = $this->createMock(CertificateRepository::class);
        $flysystem = $this->createMock(FilesystemOperator::class);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findOneBy')->willReturn($item1);

        $issuer = $this->createMock(CertificateIssuerService::class);
        $issuer->expects($this->once())
            ->method('issueCertificate')
            ->willThrowException(new \RuntimeException('Pla Plus requerit.'));

        $controller = new UserCertificateController($certRepo, $flysystem, $issuer, null, $itemRepo);
        $request = new Request([], [
            '_token' => 'valid',
            'cuis' => ['REG-2026-B01'],
            'mode' => CertificateMode::OFFICIAL->value,
        ]);
        $controller->setContainer($this->createContainerWithUser($owner, null, true, true, $request));

        $response = $controller->batchIssue($request);

        $this->assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $this->assertSame('/app_dashboard', $response->headers->get('Location'));
    }
}
