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
use App\Service\Certificate\CertificateIssuerService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class UserCertificateControllerTest extends TestCase
{
    private function createContainerWithUser(User $user, ?Environment $twig = null): ContainerInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => in_array($id, ['security.token_storage', 'twig'], true));
        $container->method('get')->willReturnCallback(function (string $id) use ($tokenStorage, $twig) {
            if ($id === 'security.token_storage') {
                return $tokenStorage;
            }
            if ($id === 'twig') {
                return $twig;
            }
            return null;
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
}
