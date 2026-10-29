<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateStatus;
use PHPUnit\Framework\TestCase;

class CertificateTest extends TestCase
{
    public function testCertificateCreationAndAttributes(): void
    {
        $user = new User('owner@registrals.cat', 'Propietari Notarial');
        $category = new Category('Joies i Rellotges', 'joies-rellotges');
        $item = new Item($user, 'REG-2026-ROLEX', 'Rolex Submariner Date', $category);

        $fingerprint = hash('sha256', 'item-fingerprint-payload');
        $pdfHash = hash('sha256', 'pdf-content-sample');

        $certificate = new Certificate(
            item: $item,
            issuedBy: $user,
            certificateNumber: 'CERT-2026-000001',
            mode: CertificateMode::DRAFT,
            pdfFilePath: 'certificates/2026/CERT-2026-000001.pdf',
            pdfSha256: $pdfHash,
            itemFingerprintAtIssue: $fingerprint,
            status: CertificateStatus::VALID
        );

        $this->assertNotNull($certificate->getUuid());
        $this->assertSame('CERT-2026-000001', $certificate->getCertificateNumber());
        $this->assertTrue($certificate->getMode()->isDraft());
        $this->assertFalse($certificate->getMode()->isOfficial());
        $this->assertTrue($certificate->isValid());
        $this->assertSame($pdfHash, $certificate->getPdfSha256());
        $this->assertSame($fingerprint, $certificate->getItemFingerprintAtIssue());

        // Verificació d'URL pública per al codi QR
        $qrUrl = $certificate->getVerificationUrl('https://registrals.cat');
        $this->assertStringStartsWith('https://registrals.cat/verify/', $qrUrl);
        $this->assertStringContainsString($certificate->getUuid()->toRfc4122(), $qrUrl);

        // Associació a l'ítem
        $item->addCertificate($certificate);
        $this->assertCount(1, $item->getCertificates());
        $this->assertSame($certificate, $item->getLatestCertificate());
        $this->assertSame($certificate, $item->getLatestCertificate(CertificateMode::DRAFT));
        $this->assertNull($item->getLatestCertificate(CertificateMode::OFFICIAL));
    }
}
