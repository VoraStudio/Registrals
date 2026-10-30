<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\VerifyCertificateController;
use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use App\Enum\ItemStatus;
use App\Repository\CertificateRepository;
use App\Service\ItemFingerprintCalculator;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

class VerifyCertificateControllerTest extends TestCase
{
    private function createContainerWithTwig(string $expectedTemplate, callable $assertParameters): ContainerInterface
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturnCallback(function (string $template, array $parameters) use ($expectedTemplate, $assertParameters) {
                $this->assertSame($expectedTemplate, $template);
                $assertParameters($parameters);
                return '<html>rendered</html>';
            });

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id) => $id === 'twig');
        $container->method('get')->willReturnCallback(fn (string $id) => $id === 'twig' ? $twig : null);

        return $container;
    }

    public function testInvalidUuidReturnsNotFound(): void
    {
        $certRepo = $this->createMock(CertificateRepository::class);
        $calc = $this->createMock(ItemFingerprintCalculator::class);

        $controller = new VerifyCertificateController($certRepo, $calc);
        $container = $this->createContainerWithTwig('verify/not_found.html.twig', function (array $params) {
            $this->assertSame('not-a-uuid', $params['identifier']);
            $this->assertStringContainsString('no és un UUID vàlid', $params['reason']);
        });
        $controller->setContainer($container);

        $response = $controller->verify('not-a-uuid');

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCertificateNotFoundReturnsNotFound(): void
    {
        $uuid = Uuid::v7()->toRfc4122();
        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('findByUuid')->willReturn(null);
        $calc = $this->createMock(ItemFingerprintCalculator::class);

        $controller = new VerifyCertificateController($certRepo, $calc);
        $container = $this->createContainerWithTwig('verify/not_found.html.twig', function (array $params) use ($uuid) {
            $this->assertSame($uuid, $params['identifier']);
            $this->assertStringContainsString('No s\'ha trobat cap certificat', $params['reason']);
        });
        $controller->setContainer($container);

        $response = $controller->verify($uuid);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testValidOfficialCertificateRendersShowViewWithMaskedName(): void
    {
        $user = new User();
        $user->setEmail('carles.sanchez@registrals.cat');
        $user->setFirstName('Carles');
        $user->setLastName('Sánchez');
        $category = new Category('Rellotges', 'rellotges');
        $item = new Item($user, 'REG-2026-ROLEX', 'Rolex Submariner', $category);

        $fingerprint = str_repeat('a', 64);
        $certUuid = Uuid::v7();
        $certificate = new Certificate(
            item: $item,
            issuedBy: $user,
            certificateNumber: 'CERT-2026-000001',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/test.pdf',
            pdfSha256: str_repeat('b', 64),
            itemFingerprintAtIssue: $fingerprint,
            status: CertificateStatus::VALID,
            uuid: $certUuid
        );

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('findByUuid')->willReturn($certificate);

        $calc = $this->createMock(ItemFingerprintCalculator::class);
        $calc->method('calculateForItem')->willReturn($fingerprint);

        $controller = new VerifyCertificateController($certRepo, $calc);
        $container = $this->createContainerWithTwig('verify/show.html.twig', function (array $params) use ($certificate) {
            $this->assertSame($certificate, $params['certificate']);
            $this->assertSame('valid', $params['verificationStatus']);
            $this->assertFalse($params['isStolen']);
            $this->assertTrue($params['isFingerprintMatching']);
            $this->assertSame('C***** S******', $params['maskedOwnerName']);
        });
        $controller->setContainer($container);

        $response = $controller->verify($certUuid->toRfc4122());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testStolenItemTriggersStolenVerificationStatus(): void
    {
        $user = new User();
        $user->setEmail('owner@registrals.cat');
        $user->setFirstName('Propietari');
        $user->setLastName('Robat');
        $category = new Category('Joies', 'joies');
        $item = new Item($user, 'REG-2026-DIAMOND', 'Anell de Diamants', $category);
        $item->setStatus(ItemStatus::STOLEN);

        $fingerprint = str_repeat('c', 64);
        $certUuid = Uuid::v7();
        $certificate = new Certificate(
            item: $item,
            issuedBy: $user,
            certificateNumber: 'CERT-2026-000002',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/test2.pdf',
            pdfSha256: str_repeat('d', 64),
            itemFingerprintAtIssue: $fingerprint,
            status: CertificateStatus::VALID,
            uuid: $certUuid
        );

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('findByUuid')->willReturn($certificate);

        $calc = $this->createMock(ItemFingerprintCalculator::class);
        $calc->method('calculateForItem')->willReturn($fingerprint);

        $controller = new VerifyCertificateController($certRepo, $calc);
        $container = $this->createContainerWithTwig('verify/show.html.twig', function (array $params) {
            $this->assertSame('stolen', $params['verificationStatus']);
            $this->assertTrue($params['isStolen']);
        });
        $controller->setContainer($container);

        $response = $controller->verify($certUuid->toRfc4122());

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
