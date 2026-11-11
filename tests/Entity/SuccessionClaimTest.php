<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\LegacyContact;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Enum\SuccessionClaimStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SuccessionClaimTest extends TestCase
{
    public function testClaimRequiresLegacyInventoryCertificate(): void
    {
        $owner = (new User())->setEmail('titular@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $category = new Category('Rellotges', 'rellotges');
        $item = new Item($owner, 'REG-2026-XYZ001', 'Omega Speedmaster', $category);

        $itemCert = new Certificate(
            item: $item,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000010',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/CERT-2026-000010.pdf',
            pdfSha256: hash('sha256', 'pdf'),
            itemFingerprintAtIssue: hash('sha256', 'fp'),
        );

        $this->expectException(InvalidArgumentException::class);

        new SuccessionClaim(
            claimReference: 'LEG-2026-000001',
            sourceCertificate: $itemCert,
            deceasedOwner: $owner,
            claimantName: 'Maria Puig',
            claimantEmail: 'maria@exemple.cat',
            claimantTaxId: '12345678Z',
            claimantEmailOtpVerifiedAt: new \DateTimeImmutable(),
            deathCertificateStorageKey: 'legacy/death.pdf',
            deathCertificateSha256: hash('sha256', 'death'),
            successionTitleStorageKey: 'legacy/title.pdf',
            successionTitleSha256: hash('sha256', 'title'),
        );
    }

    public function testClaimNoticeWindowAiScreeningAndHumanResolution(): void
    {
        $owner = (new User())->setEmail('titular@registrals.cat')->setFirstName('Anna')->setLastName('Puig');
        $gestor = (new User())->setEmail('gestor@registrals.cat')->setFirstName('Operador')->setLastName('Custòdia');
        $contact = new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat');

        $legacyCert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000011',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/CERT-2026-000011.pdf',
            pdfSha256: hash('sha256', 'pdf'),
            itemFingerprintAtIssue: hash('sha256', 'snapshot'),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
            includedItemCuisSnapshot: [['cui' => 'REG-2026-AAA111']],
            claimTokenHash: hash('sha256', 'claim-qr-secret'),
        );

        $claim = new SuccessionClaim(
            claimReference: 'LEG-2026-000002',
            sourceCertificate: $legacyCert,
            deceasedOwner: $owner,
            claimantName: 'Maria Puig',
            claimantEmail: 'MARIA@exemple.cat ',
            claimantTaxId: ' 12345678z ',
            claimantEmailOtpVerifiedAt: new \DateTimeImmutable('-5 minutes'),
            deathCertificateStorageKey: 'legacy/death.pdf',
            deathCertificateSha256: hash('sha256', 'death'),
            successionTitleStorageKey: 'legacy/title.pdf',
            successionTitleSha256: hash('sha256', 'title'),
        );

        $this->assertSame(SuccessionClaimStatus::PENDING_PAYMENT, $claim->getStatus());
        $this->assertSame('maria@exemple.cat', $claim->getClaimantEmail());
        $this->assertSame('12345678Z', $claim->getClaimantTaxId());

        $holdUntil = new \DateTimeImmutable('+72 hours');
        $claim->activateOwnerNoticeWindow(1900, 'pi_mock_123', $holdUntil);
        $this->assertSame(SuccessionClaimStatus::OWNER_NOTICE_WINDOW, $claim->getStatus());
        $this->assertTrue($claim->isSecurityHoldActive());
        $this->assertFalse($claim->isSecurityHoldActive(new \DateTimeImmutable('+73 hours')));

        $claim->markAiPrescreeningQueued();
        $this->assertSame(SuccessionClaimStatus::AI_PRESCREENING, $claim->getStatus());

        $claim->recordAiPrescreeningResult(['deceased_name' => 'Anna Puig', 'match' => true], 0.94);
        $this->assertSame(SuccessionClaimStatus::UNDER_HUMAN_REVIEW, $claim->getStatus());
        $this->assertTrue($claim->getStatus()->requiresHumanAction());
        $this->assertSame(0.94, $claim->getAiConfidenceScore());

        $claim->approve($gestor, 3, 'Documentació oficial verificada un cop vençuda la finestra preventiva.');
        $this->assertSame(SuccessionClaimStatus::APPROVED, $claim->getStatus());
        $this->assertTrue($claim->getStatus()->isResolved());
        $this->assertSame(3, $claim->getExecutedTransferCount());
        $this->assertSame($gestor->getEmail(), $claim->getReviewedByEmail());
    }
}
