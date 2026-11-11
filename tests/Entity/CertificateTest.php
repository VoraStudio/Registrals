<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\LegacyContact;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

class CertificateTest extends TestCase
{
    public function testCertificateCreationAndAttributes(): void
    {
        $user = (new User())->setEmail('owner@registrals.cat')->setFirstName('Propietari')->setLastName('Titular');
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
        $this->assertSame(CertificateScope::ITEM, $certificate->getScope());
        $this->assertFalse($certificate->isLegacyInventory());
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

    public function testLegacyInventoryCertificateSupportsNullItem(): void
    {
        $owner = (new User())->setEmail('owner@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');

        $certificate = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000099',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_CERT-2026-000099.pdf',
            pdfSha256: hash('sha256', 'legacy-pdf'),
            itemFingerprintAtIssue: hash('sha256', 'snapshot'),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
            includedItemCuisSnapshot: [['cui' => 'REG-2026-AAA111', 'name' => 'Bicicleta']],
            claimTokenHash: hash('sha256', 'claim-token')
        );

        $this->assertNull($certificate->getItem());
        $this->assertTrue($certificate->isLegacyInventory());
        $this->assertSame($contact, $certificate->getLegacyContact());

        $this->expectException(LogicException::class);
        $certificate->requireItem();
    }

    public function testItemScopedCertificateRejectsNullItem(): void
    {
        $owner = (new User())->setEmail('owner@registrals.cat')->setFirstName('Anna')->setLastName('Puig');

        $this->expectException(InvalidArgumentException::class);

        new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000100',
            mode: CertificateMode::DRAFT,
            pdfFilePath: 'certificates/2026/bad.pdf',
            pdfSha256: hash('sha256', 'pdf'),
            itemFingerprintAtIssue: hash('sha256', 'fp'),
            scope: CertificateScope::ITEM,
        );
    }
}
