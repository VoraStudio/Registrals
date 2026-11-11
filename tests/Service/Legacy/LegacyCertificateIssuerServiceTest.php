<?php

declare(strict_types=1);

namespace App\Tests\Service\Legacy;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\DTO\SignaturitTimestampDto;
use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Infrastructure\Adapter\Signature\SignaturitTimestampInterface;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\LegacyContactRepository;
use App\Service\Certificate\CertificateQrGeneratorService;
use App\Service\Certificate\PdfRendererService;
use App\Service\ItemFingerprintCalculator;
use App\Service\Legacy\LegacyCertificateIssuerService;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

class LegacyCertificateIssuerServiceTest extends TestCase
{
    private function createUser(string $email, string $firstName = 'Anna', string $lastName = 'Puig'): User
    {
        return (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);
    }

    private function createPlusOwner(bool $withRealName = true, int $quota = 10, int $used = 0): User
    {
        $plan = (new Plan('plus', 'Pla Plus', 2499))
            ->setAllowOfficialCertificates(true)
            ->setMonthlyCertificatesQuota($quota)
            ->setAllowLegacyDesignation(true)
            ->setMaxLegacyContacts(1);

        $owner = $this->createUser(
            'titular@registrals.cat',
            $withRealName ? 'Anna' : '',
            $withRealName ? 'Puig' : ''
        )
            ->setPlan($plan)
            ->setCertificatesIssuedCount($used);

        (new \ReflectionProperty(User::class, 'id'))->setValue($owner, 10);

        return $owner;
    }

    private function createService(
        ?LegacyContactRepository $legacyRepo = null,
        ?ItemRepository $itemRepo = null,
        ?CertificateRepository $certRepo = null,
        ?PdfRendererService $pdfRenderer = null,
        ?EntityManagerInterface $em = null,
        ?FilesystemOperator $storage = null,
        ?Environment $twig = null,
        ?SignaturitTimestampInterface $signaturit = null,
        ?UserActivityLogger $auditLogger = null,
    ): LegacyCertificateIssuerService {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(dirname(__DIR__, 3));

        return new LegacyCertificateIssuerService(
            legacyContactRepository: $legacyRepo ?? $this->createMock(LegacyContactRepository::class),
            itemRepository: $itemRepo ?? $this->createMock(ItemRepository::class),
            certificateRepository: $certRepo ?? $this->createMock(CertificateRepository::class),
            fingerprintCalculator: new ItemFingerprintCalculator(),
            qrGenerator: new CertificateQrGeneratorService(),
            pdfRenderer: $pdfRenderer ?? $this->createMock(PdfRendererService::class),
            entityManager: $em ?? $this->createMock(EntityManagerInterface::class),
            itemDocumentStorage: $storage ?? $this->createMock(FilesystemOperator::class),
            twig: $twig ?? $this->createMock(Environment::class),
            kernel: $kernel,
            signaturitClient: $signaturit,
            activityLogger: $auditLogger,
        );
    }

    public function testEvaluateEligibilityRejectsFreeMissingContactAndNoActiveItems(): void
    {
        $freePlan = (new Plan('free', 'Pla Free', 0))
            ->setAllowLegacyDesignation(false)
            ->setAllowOfficialCertificates(false);
        $freeUser = $this->createUser('free@registrals.cat', 'Pere', 'Vila')->setPlan($freePlan);

        $service = $this->createService();
        $resFree = $service->evaluateEmissionEligibility($freeUser);
        $this->assertFalse($resFree['canIssue']);
        $this->assertSame('plan_not_allowed', $resFree['reason']);

        $plusUserNoContact = $this->createPlusOwner();
        $resNoContact = $service->evaluateEmissionEligibility($plusUserNoContact);
        $this->assertFalse($resNoContact['canIssue']);
        $this->assertSame('no_accepted_contact', $resNoContact['reason']);

        $acceptedContact = (new LegacyContact($plusUserNoContact, 'Maria Puig', 'maria@exemple.cat'))
            ->accept($this->createUser('maria@exemple.cat', 'Maria', 'Puig'), '127.0.0.1');
        $legacyRepo = $this->createMock(LegacyContactRepository::class);
        $legacyRepo->method('findAcceptedByOwner')->willReturn([$acceptedContact]);

        $resNoItems = $this->createService(legacyRepo: $legacyRepo)->evaluateEmissionEligibility($plusUserNoContact);
        $this->assertFalse($resNoItems['canIssue']);
        $this->assertSame('no_active_items', $resNoItems['reason']);
    }

    public function testIssueLegacyInventoryCertificateRendersDualQrAndStampsEidas(): void
    {
        $owner = $this->createPlusOwner();
        $beneficiary = $this->createUser('maria@exemple.cat', 'Maria', 'Puig');
        $contact = (new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat', 'Filla'))
            ->accept($beneficiary, '127.0.0.1');

        $category = new Category('Rellotges', 'rellotges');
        $item = new Item($owner, 'REG-2026-000101', 'Omega Speedmaster', $category);

        $legacyRepo = $this->createMock(LegacyContactRepository::class);
        $legacyRepo->method('findAcceptedByOwner')->willReturn([$contact]);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findActiveForLegacyInventory')->with($owner)->willReturn([$item]);

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('findValidLegacyInventoryByOwner')->willReturn([]);
        $certRepo->method('getNextCertificateNumber')->willReturn('CERT-2026-000900');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with(
                'certificate/legacy_pdf_template.html.twig',
                $this->callback(function (array $ctx): bool {
                    $this->assertStringStartsWith('data:image/png;base64,', $ctx['verifyQrDataUri']);
                    $this->assertStringStartsWith('data:image/png;base64,', $ctx['claimQrDataUri']);
                    $this->assertStringContainsString('/verify/', $ctx['verifyUrl']);
                    $this->assertStringContainsString('/herencia/reclamar/', $ctx['claimUrl']);
                    $this->assertCount(1, $ctx['snapshotItems']);

                    return true;
                })
            )
            ->willReturn('<html><body>PDF Llegat</body></html>');

        $pdfRenderer = $this->createMock(PdfRendererService::class);
        $pdfRenderer->expects($this->once())->method('renderHtmlToPdf')->willReturn('%PDF-1.4 legacy-binary');

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->expects($this->once())->method('write')->with(
            $this->stringContains('LEGAT_CERT-2026-000900.pdf'),
            '%PDF-1.4 legacy-binary'
        );

        $signaturit = $this->createMock(SignaturitTimestampInterface::class);
        $signaturit->expects($this->once())->method('timestampHash')->willReturn(
            new SignaturitTimestampDto('tsp_legacy_123', new \DateTimeImmutable('2026-11-11T17:00:00Z'), 'mock_token')
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $auditLogger = $this->createMock(UserActivityLogger::class);
        $auditLogger->expects($this->once())->method('log')->with(
            $owner,
            UserActivityAction::LEGACY_INVENTORY_CERT_ISSUED,
            $this->stringContains('CERT-2026-000900'),
            $this->arrayHasKey('items_count')
        );

        $service = $this->createService(
            legacyRepo: $legacyRepo,
            itemRepo: $itemRepo,
            certRepo: $certRepo,
            pdfRenderer: $pdfRenderer,
            em: $em,
            storage: $storage,
            twig: $twig,
            signaturit: $signaturit,
            auditLogger: $auditLogger,
        );

        $certificate = $service->issueLegacyInventoryCertificate($owner, clientIp: '127.0.0.1');

        $this->assertTrue($certificate->isLegacyInventory());
        $this->assertNull($certificate->getItem());
        $this->assertSame(CertificateScope::LEGACY_INVENTORY, $certificate->getScope());
        $this->assertSame(CertificateMode::OFFICIAL, $certificate->getMode());
        $this->assertSame(CertificateStatus::VALID, $certificate->getStatus());
        $this->assertSame($contact, $certificate->getLegacyContact());
        $this->assertNotNull($certificate->getClaimTokenHash());
        $this->assertSame(64, strlen((string) $certificate->getClaimTokenHash()));
        $this->assertSame('tsp_legacy_123', $certificate->getEidasSignaturitId());
        $this->assertSame(1, $owner->getCertificatesIssuedCount());
        $this->assertCount(1, $certificate->getIncludedItemCuisSnapshot() ?? []);
    }

    public function testSupersedesPriorLegacyCertWhenPatrimonyChangesAndReturnsIdempotentlyWhenUnchanged(): void
    {
        $owner = $this->createPlusOwner();
        $contact = (new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat'))
            ->accept($this->createUser('maria@exemple.cat', 'Maria', 'Puig'));

        $category = new Category('Joieria', 'joieria');
        $item1 = new Item($owner, 'REG-2026-000101', 'Anell', $category);
        $item2 = new Item($owner, 'REG-2026-000102', 'Rellotge', $category);

        $legacyRepo = $this->createMock(LegacyContactRepository::class);
        $legacyRepo->method('findAcceptedByOwner')->willReturn([$contact]);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findActiveForLegacyInventory')->willReturn([$item1, $item2]);

        $tempService = $this->createService(legacyRepo: $legacyRepo, itemRepo: $itemRepo);
        $currentSnapshot = $tempService->buildInventorySnapshot($owner, $contact);

        $unchangedPrior = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000901',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_1.pdf',
            pdfSha256: str_repeat('a', 64),
            itemFingerprintAtIssue: $currentSnapshot['aggregateFingerprint'],
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
        );

        $certRepoIdempotent = $this->createMock(CertificateRepository::class);
        $certRepoIdempotent->method('findValidLegacyInventoryByOwner')->willReturn([$unchangedPrior]);
        $certRepoIdempotent->expects($this->never())->method('getNextCertificateNumber');

        $idempotentResult = $this->createService(
            legacyRepo: $legacyRepo,
            itemRepo: $itemRepo,
            certRepo: $certRepoIdempotent,
        )->issueLegacyInventoryCertificate($owner);

        $this->assertSame($unchangedPrior, $idempotentResult);

        $outdatedPrior = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000899',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_OLD.pdf',
            pdfSha256: str_repeat('b', 64),
            itemFingerprintAtIssue: str_repeat('0', 64),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
        );

        $certRepoSupersede = $this->createMock(CertificateRepository::class);
        $certRepoSupersede->method('findValidLegacyInventoryByOwner')->willReturn([$outdatedPrior]);
        $certRepoSupersede->method('getNextCertificateNumber')->willReturn('CERT-2026-000902');

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html>ok</html>');
        $pdfRenderer = $this->createMock(PdfRendererService::class);
        $pdfRenderer->method('renderHtmlToPdf')->willReturn('%PDF-1.4 updated');

        $newCert = $this->createService(
            legacyRepo: $legacyRepo,
            itemRepo: $itemRepo,
            certRepo: $certRepoSupersede,
            pdfRenderer: $pdfRenderer,
            twig: $twig,
        )->issueLegacyInventoryCertificate($owner);

        $this->assertSame(CertificateStatus::SUPERSEDED, $outdatedPrior->getStatus());
        $this->assertSame('CERT-2026-000902', $newCert->getCertificateNumber());
        $this->assertSame(CertificateStatus::VALID, $newCert->getStatus());
    }

    public function testResolveClaimByRawTokenHandlesInvalidRevokedSupersededAndValid(): void
    {
        $owner = $this->createPlusOwner();
        $contact = (new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat'))
            ->accept($this->createUser('maria@exemple.cat', 'Maria', 'Puig'));

        $rawToken = bin2hex(random_bytes(32));
        $revokedCert = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000910',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_REV.pdf',
            pdfSha256: str_repeat('e', 64),
            itemFingerprintAtIssue: str_repeat('f', 64),
            status: CertificateStatus::REVOKED,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
            claimTokenHash: hash('sha256', $rawToken),
        );

        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('findByClaimTokenHash')->with(hash('sha256', $rawToken))->willReturn($revokedCert);

        $service = $this->createService(certRepo: $certRepo);

        $this->assertSame('invalid', $service->resolveClaimByRawToken('short')['state']);
        $this->assertSame('revoked', $service->resolveClaimByRawToken($rawToken)['state']);
    }

    public function testThrowsWhenMonthlyOfficialQuotaReached(): void
    {
        $owner = $this->createPlusOwner(quota: 2, used: 2);
        $contact = (new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat'))
            ->accept($this->createUser('maria@exemple.cat', 'Maria', 'Puig'));
        $item = new Item($owner, 'REG-2026-000105', 'MacBook Pro', new Category('Tech', 'tech'));

        $legacyRepo = $this->createMock(LegacyContactRepository::class);
        $legacyRepo->method('findAcceptedByOwner')->willReturn([$contact]);
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findActiveForLegacyInventory')->willReturn([$item]);
        $certRepo = $this->createMock(CertificateRepository::class);
        $certRepo->method('findValidLegacyInventoryByOwner')->willReturn([]);

        $service = $this->createService(legacyRepo: $legacyRepo, itemRepo: $itemRepo, certRepo: $certRepo);

        $this->expectException(DomainException::class);
        $service->issueLegacyInventoryCertificate($owner);
    }
}
