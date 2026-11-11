<?php

declare(strict_types=1);

namespace App\Tests\Service\Legacy;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\DTO\AiVisionExtractionDto;
use App\Entity\Category;
use App\Entity\Certificate;
use App\Entity\Item;
use App\Entity\LegacyContact;
use App\Entity\Plan;
use App\Entity\SuccessionClaim;
use App\Entity\User;
use App\Enum\AcquisitionType;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Enum\SuccessionClaimStatus;
use App\Infrastructure\Adapter\Ai\AiVisionAnalyzerInterface;
use App\Message\AnalyzeSuccessionClaimMessage;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\PlanRepository;
use App\Repository\SuccessionClaimRepository;
use App\Repository\UserRepository;
use App\Service\Item\ItemLifecycleService;
use App\Service\Legacy\SuccessionClaimWorkflowService;
use App\Service\Plan\PlanDowngradeService;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\DefinitionBuilder;
use Symfony\Component\Workflow\MarkingStore\MethodMarkingStore;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;

class SuccessionClaimWorkflowServiceTest extends TestCase
{
    private function createUser(string $email, string $firstName, string $lastName, ?int $id = null): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);

        if ($id !== null) {
            (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);
        }

        return $user;
    }

    private function createLegacySourceCert(User $owner, ?LegacyContact $contact = null): Certificate
    {
        $contact ??= (new LegacyContact($owner, 'Maria Puig', 'maria@exemple.cat'))
            ->accept($this->createUser('maria@exemple.cat', 'Maria', 'Puig', 20), '127.0.0.1');

        return new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: 'CERT-2026-000900',
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: 'certificates/2026/LEGAT_900.pdf',
            pdfSha256: str_repeat('a', 64),
            itemFingerprintAtIssue: str_repeat('b', 64),
            status: CertificateStatus::VALID,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
            includedItemCuisSnapshot: [['cui' => 'REG-2026-0001']],
        );
    }

    private function createItemLifecycleService(EntityManagerInterface $em): ItemLifecycleService
    {
        $definition = (new DefinitionBuilder())
            ->addPlaces(['draft', 'active', 'transfer_pending', 'transferred', 'stolen', 'lost', 'archived'])
            ->addTransition(new Transition('complete_inheritance', 'active', 'active'))
            ->build();

        $workflow = new StateMachine(
            $definition,
            new MethodMarkingStore(true, 'status'),
            new EventDispatcher(),
            'item_lifecycle'
        );

        return new ItemLifecycleService($workflow, $em);
    }

    private function createService(
        ?SuccessionClaimRepository $claimRepo = null,
        ?UserRepository $userRepo = null,
        ?PlanRepository $planRepo = null,
        ?ItemRepository $itemRepo = null,
        ?EntityManagerInterface $em = null,
        ?FilesystemOperator $storage = null,
        ?AiVisionAnalyzerInterface $ai = null,
        ?MailerInterface $mailer = null,
        ?MessageBusInterface $bus = null,
        ?UserActivityLogger $auditLogger = null,
        ?PlanDowngradeService $downgradeService = null,
        ?CertificateRepository $certificateRepo = null,
    ): SuccessionClaimWorkflowService {
        $em ??= $this->createMock(EntityManagerInterface::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static function (string $route): string {
            return match ($route) {
                'app_login' => 'https://registrals.cat/login',
                'app_forgot_password' => 'https://registrals.cat/forgot-password',
                'app_profile' => 'https://registrals.cat/app/perfil',
                default => throw new \InvalidArgumentException(sprintf('Unexpected route name: %s', $route)),
            };
        });

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('$2y$10$mockhashedpassword');

        return new SuccessionClaimWorkflowService(
            claimRepository: $claimRepo ?? $this->createMock(SuccessionClaimRepository::class),
            userRepository: $userRepo ?? $this->createMock(UserRepository::class),
            planRepository: $planRepo ?? $this->createMock(PlanRepository::class),
            itemRepository: $itemRepo ?? $this->createMock(ItemRepository::class),
            entityManager: $em,
            itemDocumentStorage: $storage ?? $this->createMock(FilesystemOperator::class),
            aiAnalyzer: $ai ?? $this->createMock(AiVisionAnalyzerInterface::class),
            mailer: $mailer ?? $this->createMock(MailerInterface::class),
            urlGenerator: $urlGenerator,
            passwordHasher: $hasher,
            itemLifecycleService: $this->createItemLifecycleService($em),
            planDowngradeService: $downgradeService ?? $this->createMock(PlanDowngradeService::class),
            messageBus: $bus,
            activityLogger: $auditLogger,
            certificateRepository: $certificateRepo,
        );
    }

    public function testSubmitDocumentsStoresPdfStreamsAndPersistsClaim(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $cert = $this->createLegacySourceCert($owner);

        $tmpPdf1 = tempnam(sys_get_temp_dir(), 'death_') . '.pdf';
        $tmpPdf2 = tempnam(sys_get_temp_dir(), 'title_') . '.pdf';
        file_put_contents($tmpPdf1, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
        file_put_contents($tmpPdf2, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");

        try {
            $upload1 = new UploadedFile($tmpPdf1, 'defuncio.pdf', 'application/pdf', null, true);
            $upload2 = new UploadedFile($tmpPdf2, 'testament.pdf', 'application/pdf', null, true);

            $claimRepo = $this->createMock(SuccessionClaimRepository::class);
            $claimRepo->method('getNextClaimReference')->willReturn('LEG-2026-000010');

            $storage = $this->createMock(FilesystemOperator::class);
            $storage->expects($this->exactly(2))->method('writeStream');

            $em = $this->createMock(EntityManagerInterface::class);
            $em->expects($this->once())->method('persist');
            $em->expects($this->once())->method('flush');

            $service = $this->createService(claimRepo: $claimRepo, em: $em, storage: $storage);

            $claim = $service->submitDocumentsAndCreateClaim(
                sourceCertificate: $cert,
                claimantName: 'Maria Puig',
                claimantEmail: 'maria@exemple.cat',
                claimantTaxId: '12345678Z',
                otpVerifiedAt: new \DateTimeImmutable(),
                deathCertificateFile: $upload1,
                successionTitleFile: $upload2,
                clientIp: '127.0.0.1',
            );

            $this->assertSame('LEG-2026-000010', $claim->getClaimReference());
            $this->assertSame(SuccessionClaimStatus::PENDING_PAYMENT, $claim->getStatus());
            $this->assertStringStartsWith('legacy/claims/LEG-2026-000010/defuncio_', $claim->getDeathCertificateStorageKey());
            $this->assertStringStartsWith('legacy/claims/LEG-2026-000010/titol_', $claim->getSuccessionTitleStorageKey());
            $this->assertSame(hash_file('sha256', $tmpPdf1), $claim->getDeathCertificateSha256());
        } finally {
            @unlink($tmpPdf1);
            @unlink($tmpPdf2);
        }
    }

    public function testOnOpeningFeePaidActivates72hHoldSendsAlertAndDispatchesAiMessage(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $cert = $this->createLegacySourceCert($owner);
        $claim = new SuccessionClaim(
            'LEG-2026-000011',
            $cert,
            $owner,
            'Maria Puig',
            'maria@exemple.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000011/d.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000011/t.pdf',
            str_repeat('2', 64),
        );
        (new \ReflectionProperty(SuccessionClaim::class, 'id'))->setValue($claim, 77);

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send')->with($this->isInstanceOf(TemplatedEmail::class));

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn (object $msg): bool => $msg instanceof AnalyzeSuccessionClaimMessage && $msg->claimId === 77))
            ->willReturn(new Envelope(new AnalyzeSuccessionClaimMessage(77)));

        $service = $this->createService(mailer: $mailer, bus: $bus);
        $service->onOpeningFeePaid($claim, 1900, 'pi_test_123');

        $this->assertSame(SuccessionClaimStatus::OWNER_NOTICE_WINDOW, $claim->getStatus());
        $this->assertTrue($claim->isSecurityHoldActive());
        $this->assertSame(1900, $claim->getOpeningFeePaidCents());
        $this->assertNotNull($claim->getOwnerAlertSentAt());
    }

    public function testRunAiPrescreeningRecordsExtractionAndMovesToHumanReview(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $cert = $this->createLegacySourceCert($owner);
        $claim = new SuccessionClaim(
            'LEG-2026-000012',
            $cert,
            $owner,
            'Maria Puig',
            'maria@exemple.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000012/d.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000012/t.pdf',
            str_repeat('2', 64),
        );

        $storage = $this->createMock(FilesystemOperator::class);
        $storage->method('fileExists')->willReturn(true);
        $storage->method('read')->willReturn('%PDF-1.4 fake');

        $ai = $this->createMock(AiVisionAnalyzerInterface::class);
        $ai->expects($this->exactly(2))
            ->method('analyzeMedia')
            ->willReturn(new AiVisionExtractionDto(
                confidence: 0.92,
                rawResponse: [
                    '_parsed' => [
                        'document_kind' => 'death_certificate',
                        'deceased_full_name' => 'Anna Puig',
                        'legibility_ok' => true,
                        'anomalies' => [],
                        'confidence' => 0.92,
                    ],
                ],
            ));

        $service = $this->createService(storage: $storage, ai: $ai);
        $service->runAiPrescreening($claim);

        $this->assertSame(SuccessionClaimStatus::UNDER_HUMAN_REVIEW, $claim->getStatus());
        $this->assertSame('completed', $claim->getAiScreeningStatus());
        $this->assertSame(0.92, $claim->getAiConfidenceScore());
    }

    public function testApproveBlockedDuring72HourSecurityHoldAndExecutesBatchAfterExpiry(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $reviewer = $this->createUser('gestor@registrals.cat', 'Gestor', 'Custòdia', 99);
        $cert = $this->createLegacySourceCert($owner);

        $claim = new SuccessionClaim(
            'LEG-2026-000013',
            $cert,
            $owner,
            'Marc Soler',
            'marc.hereu@exemple.cat',
            '44556677X',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000013/d.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000013/t.pdf',
            str_repeat('2', 64),
        );
        $claim->activateOwnerNoticeWindow(1900, 'pi_1', new \DateTimeImmutable('+48 hours'));
        $claim->setStatus(SuccessionClaimStatus::UNDER_HUMAN_REVIEW);

        $serviceBlocked = $this->createService();
        try {
            $serviceBlocked->approveClaim($claim, $reviewer, 'Tot verificat correctament.');
            $this->fail('Should reject approval while 72h security hold is active');
        } catch (DomainException $e) {
            $this->assertStringContainsString('finestra preventiva', $e->getMessage());
        }

        // Ara simulem que han transcorregut les 72 hores
        $claim->activateOwnerNoticeWindow(1900, 'pi_1', new \DateTimeImmutable('-1 hour'));
        $claim->setStatus(SuccessionClaimStatus::UNDER_HUMAN_REVIEW);

        $category = new Category('Joies', 'joies');
        $item = new Item($owner, 'REG-2026-INHERIT1', 'Rellotge Familiar', $category);

        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findActiveForLegacyInventory')->with($owner)->willReturn([$item]);

        $freePlan = (new Plan('free', 'Pla Free', 0))->setMaxActiveItems(10);
        $planRepo = $this->createMock(PlanRepository::class);
        $planRepo->method('findDefaultFreePlan')->willReturn($freePlan);

        $downgrade = $this->createMock(PlanDowngradeService::class);
        $downgrade->expects($this->once())->method('checkAndEnforceQuota');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $auditLogger = $this->createMock(UserActivityLogger::class);
        $auditLogger->expects($this->exactly(2))
            ->method('log')
            ->with($this->anything(), UserActivityAction::SUCCESSION_CLAIM_APPROVED, $this->anything(), $this->anything());

        $serviceOk = $this->createService(
            planRepo: $planRepo,
            itemRepo: $itemRepo,
            mailer: $mailer,
            auditLogger: $auditLogger,
            downgradeService: $downgrade,
        );

        $count = $serviceOk->approveClaim($claim, $reviewer, 'Certificat de defunció i declaració d\'hereus verificats.');

        $this->assertSame(1, $count);
        $this->assertSame(SuccessionClaimStatus::APPROVED, $claim->getStatus());
        $this->assertSame(CertificateStatus::SUPERSEDED, $cert->getStatus());
        $this->assertSame('marc.hereu@exemple.cat', $item->getOwner()->getEmail());
        $this->assertSame(AcquisitionType::INHERITANCE, $item->getAcquisitionType());
        $this->assertNotNull($claim->getClaimantUser());
    }

    public function testRejectClaimAllowedEvenInside72hHold(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $reviewer = $this->createUser('gestor@registrals.cat', 'Gestor', 'Custòdia', 99);
        $cert = $this->createLegacySourceCert($owner);

        $claim = new SuccessionClaim(
            'LEG-2026-000014',
            $cert,
            $owner,
            'Desconegut',
            'desconegut@exemple.cat',
            '99887766A',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000014/d.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000014/t.pdf',
            str_repeat('2', 64),
        );
        $claim->activateOwnerNoticeWindow(1900, 'pi_1', new \DateTimeImmutable('+60 hours'));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $service = $this->createService(mailer: $mailer);
        $service->rejectClaim($claim, $reviewer, 'El titular viu ha revocat i denunciat ús indegut.');

        $this->assertSame(SuccessionClaimStatus::REJECTED, $claim->getStatus());
        $this->assertSame(0, $claim->getExecutedTransferCount());
    }

    public function testSubmitBeneficiaryDirectClaimWorksWithoutPdfCertAndLinksSilentCertWhenPresent(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $heir = $this->createUser('albert@exemple.cat', 'Albert', 'Soler', 25);
        $contact = (new LegacyContact($owner, 'Albert Soler', 'albert@exemple.cat'))
            ->accept($heir, '127.0.0.1');
        $cert = $this->createLegacySourceCert($owner, $contact);

        $tmpPdf1 = tempnam(sys_get_temp_dir(), 'd_') . '.pdf';
        $tmpPdf2 = tempnam(sys_get_temp_dir(), 't_') . '.pdf';
        file_put_contents($tmpPdf1, "%PDF-1.4\n%%EOF\n");
        file_put_contents($tmpPdf2, "%PDF-1.4\n%%EOF\n");

        try {
            $upload1 = new UploadedFile($tmpPdf1, 'def.pdf', 'application/pdf', null, true);
            $upload2 = new UploadedFile($tmpPdf2, 'tit.pdf', 'application/pdf', null, true);

            $claimRepo = $this->createMock(SuccessionClaimRepository::class);
            $claimRepo->method('getNextClaimReference')->willReturn('LEG-2026-000020');
            $claimRepo->method('findLatestOpenByLegacyContact')->willReturn(null);

            $certRepo = $this->createMock(CertificateRepository::class);
            $certRepo->method('findLatestValidLegacyByOwnerAndContact')->willReturn(null);

            $storage = $this->createMock(FilesystemOperator::class);
            $storage->expects($this->exactly(2))->method('writeStream');

            $em = $this->createMock(EntityManagerInterface::class);
            $em->expects($this->once())->method('persist');
            $em->expects($this->once())->method('flush');

            $serviceNoCert = $this->createService(claimRepo: $claimRepo, em: $em, storage: $storage, certificateRepo: $certRepo);
            $claimWithoutPdf = $serviceNoCert->submitBeneficiaryDirectClaim(
                contact: $contact,
                actor: $heir,
                claimantTaxId: '12345678z',
                deathCertificateFile: $upload1,
                successionTitleFile: $upload2,
                clientIp: '127.0.0.1',
            );

            $this->assertNull($claimWithoutPdf->getSourceCertificate());
            $this->assertSame($heir, $claimWithoutPdf->getClaimantUser());
            $this->assertSame($contact, $claimWithoutPdf->getLegacyContact());

            $certRepoPresent = $this->createMock(CertificateRepository::class);
            $certRepoPresent->method('findLatestValidLegacyByOwnerAndContact')->willReturn($cert);
            $upload1b = new UploadedFile($tmpPdf1, 'def.pdf', 'application/pdf', null, true);
            $upload2b = new UploadedFile($tmpPdf2, 'tit.pdf', 'application/pdf', null, true);

            $serviceWithSilentCert = $this->createService(claimRepo: $claimRepo, certificateRepo: $certRepoPresent);
            $claimWithSilentCert = $serviceWithSilentCert->submitBeneficiaryDirectClaim(
                contact: $contact,
                actor: $heir,
                claimantTaxId: '12345678z',
                deathCertificateFile: $upload1b,
                successionTitleFile: $upload2b,
            );

            $this->assertSame($cert, $claimWithSilentCert->getSourceCertificate());
        } finally {
            @unlink($tmpPdf1);
            @unlink($tmpPdf2);
        }
    }

    public function testSubmitBeneficiaryDirectClaimBlocksUnauthorizedPendingOrDuplicateOpenClaim(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $heir = $this->createUser('albert@exemple.cat', 'Albert', 'Soler', 25);
        $pendingContact = new LegacyContact($owner, 'Albert Soler', 'albert@exemple.cat');

        $tmpPdf1 = tempnam(sys_get_temp_dir(), 'd_') . '.pdf';
        $tmpPdf2 = tempnam(sys_get_temp_dir(), 't_') . '.pdf';
        file_put_contents($tmpPdf1, "%PDF-1.4\n%%EOF\n");
        file_put_contents($tmpPdf2, "%PDF-1.4\n%%EOF\n");

        try {
            $service = $this->createService();
            $upload1 = new UploadedFile($tmpPdf1, 'def.pdf', 'application/pdf', null, true);
            $upload2 = new UploadedFile($tmpPdf2, 'tit.pdf', 'application/pdf', null, true);

            try {
                $service->submitBeneficiaryDirectClaim($pendingContact, $heir, '12345678Z', $upload1, $upload2);
                $this->fail('Should reject non-accepted contact');
            } catch (DomainException $e) {
                $this->assertStringContainsString('acceptat i actiu', $e->getMessage());
            }

            $acceptedContact = (new LegacyContact($owner, 'Albert Soler', 'albert@exemple.cat'))
                ->accept($heir, '127.0.0.1');
            $existingOpen = new SuccessionClaim(
                'LEG-2026-000030',
                null,
                $owner,
                'Albert Soler',
                'albert@exemple.cat',
                '12345678Z',
                new \DateTimeImmutable(),
                'legacy/claims/LEG-2026-000030/d.pdf',
                str_repeat('1', 64),
                'legacy/claims/LEG-2026-000030/t.pdf',
                str_repeat('2', 64),
                $acceptedContact,
                $heir,
            );

            $claimRepo = $this->createMock(SuccessionClaimRepository::class);
            $claimRepo->method('findLatestOpenByLegacyContact')->willReturn($existingOpen);

            $serviceDup = $this->createService(claimRepo: $claimRepo);
            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('LEG-2026-000030');
            $serviceDup->submitBeneficiaryDirectClaim($acceptedContact, $heir, '12345678Z', $upload1, $upload2);
        } finally {
            @unlink($tmpPdf1);
            @unlink($tmpPdf2);
        }
    }

    public function testApproveClaimWorksCleanlyWithoutSourceCertificateAndTransfersItems(): void
    {
        $owner = $this->createUser('anna@registrals.cat', 'Anna', 'Puig', 1);
        $heir = $this->createUser('albert@exemple.cat', 'Albert', 'Soler', 25);
        $reviewer = $this->createUser('gestor@registrals.cat', 'Gestor', 'Custòdia', 99);
        $contact = (new LegacyContact($owner, 'Albert Soler', 'albert@exemple.cat'))
            ->accept($heir, '127.0.0.1');

        $claim = new SuccessionClaim(
            'LEG-2026-000031',
            null,
            $owner,
            'Albert Soler',
            'albert@exemple.cat',
            '12345678Z',
            new \DateTimeImmutable(),
            'legacy/claims/LEG-2026-000031/d.pdf',
            str_repeat('1', 64),
            'legacy/claims/LEG-2026-000031/t.pdf',
            str_repeat('2', 64),
            $contact,
            $heir,
        );
        $claim->activateOwnerNoticeWindow(1900, 'pi_direct', new \DateTimeImmutable('-2 hours'));
        $claim->setStatus(SuccessionClaimStatus::UNDER_HUMAN_REVIEW);

        $item = new Item($owner, 'REG-2026-DIRECT1', 'Càmera Leica', new Category('Càmeres', 'cameres'));
        $itemRepo = $this->createMock(ItemRepository::class);
        $itemRepo->method('findActiveForLegacyInventory')->willReturn([$item]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($heir);

        $service = $this->createService(userRepo: $userRepo, itemRepo: $itemRepo);
        $count = $service->approveClaim($claim, $reviewer, 'Expedient directe sense PDF verificat correctament.');

        $this->assertSame(1, $count);
        $this->assertNull($claim->getSourceCertificate());
        $this->assertSame(SuccessionClaimStatus::APPROVED, $claim->getStatus());
        $this->assertSame($heir, $item->getOwner());
    }
}

