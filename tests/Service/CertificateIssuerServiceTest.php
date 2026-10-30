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

        $issuerService = new CertificateIssuerService(
            twig: $twig,
            pdfRenderer: $pdfRenderer,
            qrGenerator: $qrGenerator,
            fingerprintCalculator: $calculator,
            certificateRepository: $certificateRepository,
            entityManager: $entityManager,
            itemDocumentStorage: $storage,
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

        $issuerService = new CertificateIssuerService(
            twig: $twig,
            pdfRenderer: $pdfRenderer,
            qrGenerator: $qrGenerator,
            fingerprintCalculator: $calculator,
            certificateRepository: $certificateRepository,
            entityManager: $entityManager,
            itemDocumentStorage: $storage,
            defaultBaseUrl: 'https://registrals.cat',
            logger: new NullLogger()
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El teu pla actual no permet l\'emissió de certificats oficials');

        $issuerService->issueCertificate($item, $user, CertificateMode::OFFICIAL);
    }
}
