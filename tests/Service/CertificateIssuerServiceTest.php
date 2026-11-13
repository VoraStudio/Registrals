<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use App\Repository\CertificateRepository;
use App\Service\Certificate\CertificateIssuerService;
use App\Service\Certificate\CertificateQrGeneratorService;
use App\Service\Certificate\PdfRendererService;
use App\Service\ItemFingerprintCalculator;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

class CertificateIssuerServiceTest extends TestCase
{
    public function testIssueDraftCertificateSuccessfully(): void
    {
        $user = new User('owner@registrals.cat', 'Propietari Free');
        $plan = new Plan('free', 'Pla Free', 0, 20, 1, 1);
        $plan->setAllowOfficialCertificates(false);
        $user->setPlan($plan);

        $category = new Category('Art', 'art');
        $item = new Item($user, 'REG-2026-PICASSO', 'Litografia Original', $category);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html><body>Certificat</body></html>');

        $pdfRenderer = $this->createMock(PdfRendererService::class);
        $pdfRenderer->method('renderHtmlToPdf')->willReturn('%PDF-1.4 sample binary content');

        $qrGenerator = new CertificateQrGeneratorService();

        $calculator = $this->createMock(ItemFingerprintCalculator::class);
        $calculator->method('calculateForItem')->willReturn(str_repeat('f', 64));

        $certificateRepository = $this->createMock(CertificateRepository::class);
        $certificateRepository->method('getNextCertificateNumber')->willReturn('CERT-2026-000001');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('write');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist');
        $entityManager->expects($this->once())->method('flush');

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(__DIR__ . '/../../');

        $issuerService = new CertificateIssuerService(
            twig: $twig,
            pdfRenderer: $pdfRenderer,
            qrGenerator: $qrGenerator,
            fingerprintCalculator: $calculator,
            certificateRepository: $certificateRepository,
            entityManager: $entityManager,
            itemDocumentStorage: $storage,
            kernel: $kernel,
            defaultBaseUrl: 'https://registrals.cat',
            logger: new NullLogger()
        );

        $certificate = $issuerService->issueCertificate($item, $user, CertificateMode::DRAFT);

        $this->assertInstanceOf(Certificate::class, $certificate);
        $this->assertSame('CERT-2026-000001', $certificate->getCertificateNumber());
        $this->assertSame(CertificateMode::DRAFT, $certificate->getMode());
        $this->assertSame(CertificateStatus::VALID, $certificate->getStatus());
        $this->assertSame(hash('sha256', '%PDF-1.4 sample binary content'), $certificate->getPdfSha256());
        $this->assertSame(str_repeat('f', 64), $certificate->getItemFingerprintAtIssue());
        // El pla free no s'hauria d'incrementar en mode esborrany
        $this->assertSame(0, $user->getCertificatesIssuedCount());
    }

    public function testIssueOfficialCertificateThrowsWhenNotAllowedInPlan(): void
    {
        $user = new User('owner@registrals.cat', 'Propietari Free');
        $plan = new Plan('free', 'Pla Free', 0, 20, 1, 1);
        $plan->setAllowOfficialCertificates(false);
        $user->setPlan($plan);

        $category = new Category('Art', 'art');
        $item = new Item($user, 'REG-2026-PICASSO', 'Litografia Original', $category);

        $twig = $this->createMock(Environment::class);
        $pdfRenderer = $this->createMock(PdfRendererService::class);
        $qrGenerator = new CertificateQrGeneratorService();
        $calculator = $this->createMock(ItemFingerprintCalculator::class);
        $certificateRepository = $this->createMock(CertificateRepository::class);
        $storage = $this->createMock(FilesystemOperator::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(__DIR__ . '/../../');

        $issuerService = new CertificateIssuerService(
            twig: $twig,
            pdfRenderer: $pdfRenderer,
            qrGenerator: $qrGenerator,
            fingerprintCalculator: $calculator,
            certificateRepository: $certificateRepository,
            entityManager: $entityManager,
            itemDocumentStorage: $storage,
            kernel: $kernel,
            defaultBaseUrl: 'https://registrals.cat',
            logger: new NullLogger()
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El teu pla actual no permet l\'emissió de certificats oficials');

        $issuerService->issueCertificate($item, $user, CertificateMode::OFFICIAL);
    }

    public function testIssueOfficialCertificateRequiresApprovedKycForNonAdmin(): void
    {
        $user = new User();
        $user->setEmail('plus-unverified@registrals.cat');
        $plan = (new Plan('plus', 'Pla Plus', 2200))
            ->setAllowOfficialCertificates(true)
            ->setMonthlyCertificatesQuota(-1);
        $user->setPlan($plan);

        $category = new Category('Rellotges', 'rellotges');
        $item = new Item($user, 'REG-2026-ROLEX', 'Rolex Submariner', $category);

        $issuerService = new CertificateIssuerService(
            twig: $this->createMock(Environment::class),
            pdfRenderer: $this->createMock(PdfRendererService::class),
            qrGenerator: new CertificateQrGeneratorService(),
            fingerprintCalculator: $this->createMock(ItemFingerprintCalculator::class),
            certificateRepository: $this->createMock(CertificateRepository::class),
            entityManager: $this->createMock(EntityManagerInterface::class),
            itemDocumentStorage: $this->createMock(FilesystemOperator::class),
            kernel: $this->createMock(KernelInterface::class),
            defaultBaseUrl: 'https://registrals.cat',
            logger: new NullLogger()
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('verificació oficial d\'identitat');

        $issuerService->issueCertificate($item, $user, CertificateMode::OFFICIAL);
    }

    public function testIssueOfficialCertificateSucceedsWhenKycIsApproved(): void
    {
        $user = (new User())
            ->setEmail('plus-verified@registrals.cat')
            ->setKycStatus(\App\Enum\KycStatus::APPROVED);
        $plan = (new Plan('plus', 'Pla Plus', 2200))
            ->setAllowOfficialCertificates(true)
            ->setMonthlyCertificatesQuota(-1);
        $user->setPlan($plan);

        $category = new Category('Rellotges', 'rellotges');
        $item = new Item($user, 'REG-2026-OMEGA', 'Omega Speedmaster', $category);

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html><body>Oficial</body></html>');

        $pdfRenderer = $this->createMock(PdfRendererService::class);
        $pdfRenderer->method('renderHtmlToPdf')->willReturn('%PDF-1.4 official');

        $calculator = $this->createMock(ItemFingerprintCalculator::class);
        $calculator->method('calculateForItem')->willReturn(str_repeat('a', 64));

        $certificateRepository = $this->createMock(CertificateRepository::class);
        $certificateRepository->method('getNextCertificateNumber')->willReturn('CERT-2026-000100');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('write');

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(__DIR__ . '/../../');

        $signaturit = $this->createMock(\App\Infrastructure\Adapter\Signature\SignaturitTimestampInterface::class);
        $signaturit->expects($this->once())
            ->method('timestampHash')
            ->willReturn(new \App\DTO\SignaturitTimestampDto('eIDAS-SIG-TEST', new \DateTimeImmutable()));

        $issuerService = new CertificateIssuerService(
            twig: $twig,
            pdfRenderer: $pdfRenderer,
            qrGenerator: new CertificateQrGeneratorService(),
            fingerprintCalculator: $calculator,
            certificateRepository: $certificateRepository,
            entityManager: $this->createMock(EntityManagerInterface::class),
            itemDocumentStorage: $storage,
            kernel: $kernel,
            signaturitClient: $signaturit,
            defaultBaseUrl: 'https://registrals.cat',
            logger: new NullLogger()
        );

        $cert = $issuerService->issueCertificate($item, $user, CertificateMode::OFFICIAL);

        $this->assertSame('CERT-2026-000100', $cert->getCertificateNumber());
        $this->assertSame(CertificateMode::OFFICIAL, $cert->getMode());
        $this->assertSame(1, $user->getCertificatesIssuedCount());
    }
}
