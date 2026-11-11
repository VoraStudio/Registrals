<?php

declare(strict_types=1);

namespace App\Service\Legacy;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\SuccessionClaim;
use App\Entity\User;
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
use App\Service\Plan\PlanDowngradeService;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Orquestra el cicle complet d'un expedient successori post-mortem:
 * 1) Custòdia Flysystem + hashes SHA-256 dels documents probatoris.
 * 2) Activació de finestra preventiva de 72 hores amb avís urgent al titular en abonar-se la taxa.
 * 3) Pre-triatge OCR/visió sobirà local amb Qwen-VL (`legacy_claim`, mai decisori).
 * 4) Resolució motivada per ROLE_GESTOR i traspàs atòmic per lot cap al compte receptor Pla Free/existent.
 */
class SuccessionClaimWorkflowService
{
    public const SECURITY_HOLD_HOURS = 72;
    public const MAX_DOCUMENT_BYTES = 15728640; // 15 MB

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly SuccessionClaimRepository $claimRepository,
        private readonly UserRepository $userRepository,
        private readonly PlanRepository $planRepository,
        private readonly ItemRepository $itemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemOperator $itemDocumentStorage,
        private readonly AiVisionAnalyzerInterface $aiAnalyzer,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ItemLifecycleService $itemLifecycleService,
        private readonly PlanDowngradeService $planDowngradeService,
        private readonly ?MessageBusInterface $messageBus = null,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly string $mailerFromAddress = 'info@registrals.com',
        private readonly string $mailerFromName = 'Registrals',
        private readonly ?LoggerInterface $logger = null,
        private readonly ?CertificateRepository $certificateRepository = null,
    ) {
    }

    public function submitDocumentsAndCreateClaim(
        Certificate $sourceCertificate,
        string $claimantName,
        string $claimantEmail,
        string $claimantTaxId,
        \DateTimeImmutable $otpVerifiedAt,
        UploadedFile $deathCertificateFile,
        UploadedFile $successionTitleFile,
        ?string $clientIp = null,
    ): SuccessionClaim {
        if (!$sourceCertificate->isLegacyInventory() || !$sourceCertificate->isValid()) {
            throw new DomainException('Només es poden obrir expedients sobre un Certificat d\'Inventari Global de Llegat vigent.');
        }

        $cleanEmail = mb_strtolower(trim($claimantEmail));
        $existingClaimantUser = $this->userRepository->findOneBy(['email' => $cleanEmail]);

        return $this->persistClaimWithDocuments(
            sourceCertificate: $sourceCertificate,
            deceasedOwner: $sourceCertificate->getIssuedBy(),
            legacyContact: $sourceCertificate->getLegacyContact(),
            claimantUser: $existingClaimantUser,
            claimantName: $claimantName,
            claimantEmail: $cleanEmail,
            claimantTaxId: $claimantTaxId,
            otpVerifiedAt: $otpVerifiedAt,
            deathCertificateFile: $deathCertificateFile,
            successionTitleFile: $successionTitleFile,
            channel: 'qr_portal',
            clientIp: $clientIp,
        );
    }

    /**
     * Obre un expedient successori autenticat directament des del compte d'una Persona de Confiança
     * acceptada (Art. 96 LOPDGDD), sense requerir el PDF físic del titular. Si existeix un certificat
     * d'Inventari Global vigent vinculat, s'associa silenciosament en segon pla.
     */
    public function submitBeneficiaryDirectClaim(
        LegacyContact $contact,
        User $actor,
        string $claimantTaxId,
        UploadedFile $deathCertificateFile,
        UploadedFile $successionTitleFile,
        ?string $clientIp = null,
    ): SuccessionClaim {
        if (!$contact->isDesignatedUser($actor)) {
            throw new DomainException('No tens permisos per obrir un expedient sobre aquesta designació.');
        }

        if ($actor->getId() !== null && $actor->getId() === $contact->getOwner()->getId()) {
            throw new DomainException('No pots iniciar un expedient successori sobre el teu propi patrimoni.');
        }

        if (!$contact->getStatus()->isAccepted()) {
            throw new DomainException('Només les designacions en estat acceptat i actiu poden iniciar un expedient successori.');
        }

        $openClaim = $this->claimRepository->findLatestOpenByLegacyContact($contact);
        if ($openClaim instanceof SuccessionClaim) {
            throw new DomainException(sprintf(
                'Ja existeix l\'expedient %s en curs per a aquesta designació.',
                $openClaim->getClaimReference()
            ));
        }

        $linkedCertificate = $this->certificateRepository?->findLatestValidLegacyByOwnerAndContact(
            $contact->getOwner(),
            $contact
        );

        $claimantName = trim($actor->getFullName()) !== '' ? $actor->getFullName() : $contact->getFullName();

        return $this->persistClaimWithDocuments(
            sourceCertificate: $linkedCertificate,
            deceasedOwner: $contact->getOwner(),
            legacyContact: $contact,
            claimantUser: $actor,
            claimantName: $claimantName,
            claimantEmail: $actor->getEmail(),
            claimantTaxId: $claimantTaxId,
            otpVerifiedAt: $actor->getEmailVerifiedAt() ?? new \DateTimeImmutable(),
            deathCertificateFile: $deathCertificateFile,
            successionTitleFile: $successionTitleFile,
            channel: 'beneficiary_profile',
            clientIp: $clientIp,
        );
    }

    public function cancelPendingDraft(SuccessionClaim $claim, User $actor): void
    {
        if ($claim->getStatus() !== SuccessionClaimStatus::PENDING_PAYMENT) {
            throw new DomainException('Només es poden cancel·lar esborranys pendents de pagament.');
        }

        $isOwnerOfDraft = $claim->getClaimantUser()?->getId() !== null
            && $actor->getId() !== null
            && $claim->getClaimantUser()->getId() === $actor->getId();
        $isContact = $claim->getLegacyContact()?->isDesignatedUser($actor) ?? false;

        if (!$isOwnerOfDraft && !$isContact) {
            throw new DomainException('No tens permisos per cancel·lar aquest esborrany d\'expedient.');
        }

        foreach ([$claim->getDeathCertificateStorageKey(), $claim->getSuccessionTitleStorageKey()] as $key) {
            try {
                if ($this->itemDocumentStorage->fileExists($key)) {
                    $this->itemDocumentStorage->delete($key);
                }
            } catch (\Throwable) {
                // La neteja de fitxers temporals d'esborrany no bloqueja el reinici del tràmit
            }
        }

        $this->entityManager->remove($claim);
        $this->entityManager->flush();
    }

    private function persistClaimWithDocuments(
        ?Certificate $sourceCertificate,
        User $deceasedOwner,
        ?LegacyContact $legacyContact,
        ?User $claimantUser,
        string $claimantName,
        string $claimantEmail,
        string $claimantTaxId,
        \DateTimeImmutable $otpVerifiedAt,
        UploadedFile $deathCertificateFile,
        UploadedFile $successionTitleFile,
        string $channel,
        ?string $clientIp = null,
    ): SuccessionClaim {
        $cleanName = trim($claimantName);
        $cleanEmail = mb_strtolower(trim($claimantEmail));
        $cleanTaxId = strtoupper(trim($claimantTaxId));

        if ($cleanName === '' || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($cleanTaxId) < 5) {
            throw new DomainException('Cal indicar el nom complet, un correu electrònic vàlid i el document d\'identitat del sol·licitant (mínim 5 caràcters).');
        }

        $claimReference = $this->claimRepository->getNextClaimReference();

        [$deathKey, $deathSha256] = $this->storePrivateSuccessionDocument($claimReference, 'defuncio', $deathCertificateFile);
        [$titleKey, $titleSha256] = $this->storePrivateSuccessionDocument($claimReference, 'titol', $successionTitleFile);

        $claim = new SuccessionClaim(
            claimReference: $claimReference,
            sourceCertificate: $sourceCertificate,
            deceasedOwner: $deceasedOwner,
            claimantName: $cleanName,
            claimantEmail: $cleanEmail,
            claimantTaxId: $cleanTaxId,
            claimantEmailOtpVerifiedAt: $otpVerifiedAt,
            deathCertificateStorageKey: $deathKey,
            deathCertificateSha256: $deathSha256,
            successionTitleStorageKey: $titleKey,
            successionTitleSha256: $titleSha256,
            legacyContact: $legacyContact,
            claimantUser: $claimantUser,
        );

        $this->entityManager->persist($claim);
        $this->entityManager->flush();

        $this->recordAudit(
            $deceasedOwner,
            UserActivityAction::SUCCESSION_CLAIM_SUBMITTED,
            sprintf('Registre documental d\'expedient successori %s per %s (%s)', $claimReference, $cleanName, $cleanEmail),
            [
                'claim_uuid' => $claim->getUuid()->toRfc4122(),
                'claim_reference' => $claimReference,
                'source_certificate' => $sourceCertificate?->getCertificateNumber(),
                'channel' => $channel,
                'claimant_email' => $cleanEmail,
                'death_cert_sha256' => $deathSha256,
                'succession_title_sha256' => $titleSha256,
                'ip' => $clientIp,
            ]
        );

        return $claim;
    }

    public function onOpeningFeePaid(SuccessionClaim $claim, int $paidCents, ?string $paymentIntentId = null): void
    {
        if ($claim->getStatus() !== SuccessionClaimStatus::PENDING_PAYMENT) {
            return;
        }

        $holdUntil = (new \DateTimeImmutable())->modify(sprintf('+%d hours', self::SECURITY_HOLD_HOURS));
        $claim->activateOwnerNoticeWindow($paidCents, $paymentIntentId, $holdUntil);
        $this->entityManager->flush();

        $this->sendOwnerPreventiveAlertEmail($claim, $holdUntil);

        $this->recordAudit(
            $claim->getDeceasedOwner(),
            UserActivityAction::SUCCESSION_CLAIM_SUBMITTED,
            sprintf(
                'Taxa d\'obertura abonada per a l\'expedient %s (%s €). Finestra preventiva de %d h activada.',
                $claim->getClaimReference(),
                number_format($paidCents / 100, 2, ',', '.'),
                self::SECURITY_HOLD_HOURS
            ),
            [
                'claim_uuid' => $claim->getUuid()->toRfc4122(),
                'claim_reference' => $claim->getClaimReference(),
                'paid_cents' => $paidCents,
                'security_hold_until' => $holdUntil->format(\DateTimeInterface::ATOM),
            ]
        );

        if ($claim->getId() !== null && $this->messageBus !== null) {
            try {
                $this->messageBus->dispatch(new AnalyzeSuccessionClaimMessage($claim->getId()));
            } catch (\Throwable $e) {
                $this->logger?->warning('No s\'ha pogut encuar el pre-triatge IA d\'expedient successori: ' . $e->getMessage(), [
                    'claim_reference' => $claim->getClaimReference(),
                ]);
            }
        }
    }

    public function runAiPrescreening(SuccessionClaim $claim): void
    {
        if ($claim->getStatus()->isResolved()) {
            return;
        }

        $claim->markAiPrescreeningQueued();
        $this->entityManager->flush();

        try {
            $deathParsed = $this->analyzeStoredDocument($claim->getDeathCertificateStorageKey());
            $titleParsed = $this->analyzeStoredDocument($claim->getSuccessionTitleStorageKey());

            $alerts = [];
            $ownerName = $claim->getDeceasedOwner()->getFullName();
            $extractedDeceased = (string) ($deathParsed['deceased_full_name'] ?? $titleParsed['deceased_full_name'] ?? '');

            if ($extractedDeceased === '') {
                $alerts[] = 'No s\'ha pogut identificar amb nitidesa el nom del causant als documents aportats; cal comprovació manual completa.';
            } elseif (!$this->namesRoughlyMatch($ownerName, $extractedDeceased)) {
                $alerts[] = sprintf(
                    'Discrepància potencial de titular: el registre indica "%s" però l\'OCR ha detectat "%s".',
                    $ownerName,
                    $extractedDeceased
                );
            }

            if (($deathParsed['legibility_ok'] ?? true) === false) {
                $alerts[] = 'Llegibilitat limitada detectada al certificat de defunció.';
            }
            if (($titleParsed['legibility_ok'] ?? true) === false) {
                $alerts[] = 'Llegibilitat limitada detectada al títol successori.';
            }

            $contactMatch = $claim->getLegacyContact() !== null
                && mb_strtolower($claim->getLegacyContact()->getEmail()) === $claim->getClaimantEmail();

            if (!$contactMatch) {
                $alerts[] = 'El correu electrònic del sol·licitant no coincideix amb la Persona de Confiança designada; cal verificar la legitimació com a hereu/marmessor al títol successori.';
            }

            foreach ([$deathParsed['anomalies'] ?? [], $titleParsed['anomalies'] ?? []] as $docAnomalies) {
                if (is_array($docAnomalies)) {
                    foreach ($docAnomalies as $anom) {
                        if (is_string($anom) && trim($anom) !== '') {
                            $alerts[] = trim($anom);
                        }
                    }
                }
            }

            $conf1 = is_numeric($deathParsed['confidence'] ?? null) ? (float) $deathParsed['confidence'] : 0.0;
            $conf2 = is_numeric($titleParsed['confidence'] ?? null) ? (float) $titleParsed['confidence'] : 0.0;
            $avgConfidence = round(($conf1 + $conf2) / 2, 3);

            $combined = [
                'model' => 'Qwen-VL (execució local privada SGLang)',
                'analyzed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'matches_designated_contact_email' => $contactMatch,
                'death_certificate' => $deathParsed,
                'succession_title' => $titleParsed,
                'warnings' => array_values(array_unique($alerts)),
            ];

            $claim->recordAiPrescreeningResult($combined, $avgConfidence);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger?->warning('Error en pre-triatge IA d\'expedient successori: ' . $e->getMessage(), [
                'claim_reference' => $claim->getClaimReference(),
            ]);
            $claim->markAiPrescreeningFailed();
            $this->entityManager->flush();
        }
    }

    public function approveClaim(SuccessionClaim $claim, User $reviewer, string $resolutionNotes): int
    {
        if ($claim->getStatus()->isResolved()) {
            throw new DomainException('Aquest expedient ja es troba resolt.');
        }

        if ($claim->getStatus() === SuccessionClaimStatus::PENDING_PAYMENT) {
            throw new DomainException('Aquest expedient encara no ha abonat la taxa d\'obertura.');
        }

        if ($claim->isSecurityHoldActive()) {
            $remainingHours = max(1, (int) ceil(($claim->getSecurityHoldUntil()->getTimestamp() - time()) / 3600));
            throw new DomainException(sprintf(
                'La finestra preventiva anti-frau de %d hores continua activa (falten aproximadament %d h). No es pot aprovar fins que caduqui.',
                self::SECURITY_HOLD_HOURS,
                $remainingHours
            ));
        }

        $cleanNotes = trim($resolutionNotes);
        if (mb_strlen($cleanNotes) < 10) {
            throw new DomainException('Cal redactar una justificació motivada d\'almenys 10 caràcters per aprovar l\'expedient.');
        }

        [$heirUser, $provisionedNewAccount] = $this->resolveOrProvisionHeirUser($claim);
        $activeItems = $this->itemRepository->findActiveForLegacyInventory($claim->getDeceasedOwner());

        $transferredCount = 0;
        foreach ($activeItems as $item) {
            $this->itemLifecycleService->completeInheritanceTransfer($item, $heirUser);
            ++$transferredCount;
        }

        $claim->approve($reviewer, $transferredCount, $cleanNotes);
        $claim->getSourceCertificate()?->setStatus(CertificateStatus::SUPERSEDED);

        $this->entityManager->flush();
        $this->planDowngradeService->checkAndEnforceQuota($heirUser);

        $details = [
            'claim_uuid' => $claim->getUuid()->toRfc4122(),
            'claim_reference' => $claim->getClaimReference(),
            'executed_transfer_count' => $transferredCount,
            'heir_uuid' => $heirUser->getUuid()->toRfc4122(),
            'heir_email' => $heirUser->getEmail(),
            'reviewed_by' => $reviewer->getEmail(),
            'provisioned_new_account' => $provisionedNewAccount,
        ];

        $this->recordAudit(
            $claim->getDeceasedOwner(),
            UserActivityAction::SUCCESSION_CLAIM_APPROVED,
            sprintf(
                'Expedient successori %s aprovat per %s: %d bé(ns) traspassats a %s',
                $claim->getClaimReference(),
                $reviewer->getEmail(),
                $transferredCount,
                $heirUser->getEmail()
            ),
            $details
        );

        $this->recordAudit(
            $heirUser,
            UserActivityAction::SUCCESSION_CLAIM_APPROVED,
            sprintf('Recepció patrimonial per successió acreditada (%s): %d bé(ns) assignats', $claim->getClaimReference(), $transferredCount),
            $details
        );

        $this->sendClaimantResolutionEmail($claim, approved: true, provisionedNewAccount: $provisionedNewAccount);

        return $transferredCount;
    }

    public function rejectClaim(SuccessionClaim $claim, User $reviewer, string $resolutionNotes): void
    {
        if ($claim->getStatus()->isResolved()) {
            throw new DomainException('Aquest expedient ja es troba resolt.');
        }

        $cleanNotes = trim($resolutionNotes);
        if (mb_strlen($cleanNotes) < 10) {
            throw new DomainException('Cal indicar el motiu detallat del rebuig (mínim 10 caràcters).');
        }

        $claim->reject($reviewer, $cleanNotes);
        $this->entityManager->flush();

        $this->recordAudit(
            $claim->getDeceasedOwner(),
            UserActivityAction::SUCCESSION_CLAIM_REJECTED,
            sprintf('Expedient successori %s rebutjat per %s: %s', $claim->getClaimReference(), $reviewer->getEmail(), $cleanNotes),
            [
                'claim_uuid' => $claim->getUuid()->toRfc4122(),
                'claim_reference' => $claim->getClaimReference(),
                'reviewed_by' => $reviewer->getEmail(),
            ]
        );

        $this->sendClaimantResolutionEmail($claim, approved: false, provisionedNewAccount: false);
    }

    /**
     * @return array{0: string, 1: string} [storageKey, sha256]
     */
    private function storePrivateSuccessionDocument(string $claimReference, string $kind, UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false || !file_exists($realPath)) {
            throw new DomainException('No s\'ha pogut llegir el document carregat.');
        }

        $size = $file->getSize() ?: filesize($realPath);
        if ($size === false || $size <= 0 || $size > self::MAX_DOCUMENT_BYTES) {
            throw new DomainException('Cada document ha de pesar com a màxim 15 MB i no pot estar buit.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) ($finfo->file($realPath) ?: $file->getClientMimeType());
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new DomainException(sprintf('Format documental no admès (%s). Utilitza PDF, JPG, PNG o WebP.', $mimeType));
        }

        $sha256 = hash_file('sha256', $realPath);
        if ($sha256 === false) {
            throw new DomainException('No s\'ha pogut calcular l\'empremta criptogràfica SHA-256 del document.');
        }

        $ext = match ($mimeType) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $storageKey = sprintf('legacy/claims/%s/%s_%s.%s', $claimReference, $kind, bin2hex(random_bytes(8)), $ext);

        $stream = fopen($realPath, 'rb');
        if ($stream === false) {
            throw new DomainException('No s\'ha pogut obrir el flux temporal del document per desar-lo en custòdia.');
        }

        try {
            $this->itemDocumentStorage->writeStream($storageKey, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [$storageKey, $sha256];
    }

    /**
     * @return array<string, mixed>
     */
    private function analyzeStoredDocument(string $storageKey): array
    {
        if (!$this->itemDocumentStorage->fileExists($storageKey)) {
            throw new DomainException(sprintf('Document custodiat inexistent: %s', $storageKey));
        }

        $binary = $this->itemDocumentStorage->read($storageKey);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->buffer($binary) ?: 'application/pdf');
        if ($mime === 'application/octet-stream' && str_ends_with($storageKey, '.pdf')) {
            $mime = 'application/pdf';
        }

        $dto = $this->aiAnalyzer->analyzeMedia($binary, $mime, 'legacy_claim');
        $parsed = $dto->rawResponse['_parsed'] ?? null;

        if (!is_array($parsed)) {
            return [
                'confidence' => $dto->confidence,
                'notes' => $dto->notes,
                'legibility_ok' => $dto->confidence > 0.0,
            ];
        }

        return $parsed;
    }

    /**
     * @return array{0: User, 1: bool} [user, provisionedNew]
     */
    private function resolveOrProvisionHeirUser(SuccessionClaim $claim): array
    {
        $existing = $this->userRepository->findOneBy(['email' => $claim->getClaimantEmail()]);
        if ($existing instanceof User) {
            if ($claim->getClaimantUser() === null) {
                $claim->setClaimantUser($existing);
            }

            return [$existing, false];
        }

        $parts = preg_split('/\s+/', trim($claim->getClaimantName()), 2);
        $firstName = trim((string) ($parts[0] ?? 'Hereu'));
        $lastName = trim((string) ($parts[1] ?? 'Registrals'));
        if ($lastName === '') {
            $lastName = $firstName;
        }

        $freePlan = $this->planRepository->findDefaultFreePlan();

        $user = (new User())
            ->setEmail($claim->getClaimantEmail())
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setTaxId($claim->getClaimantTaxId())
            ->setIsActive(true)
            ->setAuthProvider('local')
            ->setRoles(['ROLE_USER']);

        if ($freePlan !== null) {
            $user->setPlan($freePlan)
                ->setPlanStartedAt(new \DateTimeImmutable())
                ->setPeriodStartedAt(new \DateTimeImmutable());
        }

        $user->markEmailVerified($claim->getClaimantEmailOtpVerifiedAt());
        $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));

        $this->entityManager->persist($user);
        $claim->setClaimantUser($user);

        return [$user, true];
    }

    private function namesRoughlyMatch(string $expected, string $extracted): bool
    {
        $normalize = static function (string $value): array {
            $clean = mb_strtolower($value);
            $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $clean);
            if ($trans !== false) {
                $clean = $trans;
            }
            $tokens = preg_split('/[^a-z0-9]+/', $clean, -1, PREG_SPLIT_NO_EMPTY);

            return array_values(array_filter($tokens ?: [], static fn (string $t): bool => strlen($t) >= 3));
        };

        $expectedTokens = $normalize($expected);
        $extractedTokens = $normalize($extracted);
        if ($expectedTokens === [] || $extractedTokens === []) {
            return false;
        }

        $matches = count(array_intersect($expectedTokens, $extractedTokens));

        return $matches >= min(2, count($expectedTokens));
    }

    private function sendOwnerPreventiveAlertEmail(SuccessionClaim $claim, \DateTimeImmutable $holdUntil): void
    {
        $owner = $claim->getDeceasedOwner();

        try {
            $profileUrl = $this->urlGenerator->generate('app_profile', [], UrlGeneratorInterface::ABSOLUTE_URL) . '#herencia-digital';

            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address($owner->getEmail(), $owner->getFullName()))
                ->subject(sprintf('AVÍS URGENT DE SEGURETAT · Obertura d\'expedient %s — Registrals', $claim->getClaimReference()))
                ->htmlTemplate('emails/legacy_owner_preventive_alert.html.twig')
                ->textTemplate('emails/legacy_owner_preventive_alert.txt.twig')
                ->context([
                    'claim' => $claim,
                    'owner' => $owner,
                    'holdUntil' => $holdUntil,
                    'profileUrl' => $profileUrl,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error enviant alerta preventiva 72 h al titular: ' . $e->getMessage(), [
                'claim_reference' => $claim->getClaimReference(),
            ]);
        }
    }

    private function sendClaimantResolutionEmail(SuccessionClaim $claim, bool $approved, bool $provisionedNewAccount): void
    {
        try {
            $loginUrl = $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $forgotUrl = $this->urlGenerator->generate('app_forgot_password', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFromAddress, $this->mailerFromName))
                ->to(new Address($claim->getClaimantEmail(), $claim->getClaimantName()))
                ->subject($approved
                    ? sprintf('Expedient %s aprovat: patrimoni transferit — Registrals', $claim->getClaimReference())
                    : sprintf('Resolució de l\'expedient successori %s — Registrals', $claim->getClaimReference()))
                ->htmlTemplate('emails/legacy_claim_resolution.html.twig')
                ->textTemplate('emails/legacy_claim_resolution.txt.twig')
                ->context([
                    'claim' => $claim,
                    'approved' => $approved,
                    'provisionedNewAccount' => $provisionedNewAccount,
                    'loginUrl' => $loginUrl,
                    'forgotUrl' => $forgotUrl,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error enviant correu de resolució al reclamant: ' . $e->getMessage(), [
                'claim_reference' => $claim->getClaimReference(),
            ]);
        }
    }

    /**
     * @param array<string, mixed>|null $details
     */
    private function recordAudit(User $user, UserActivityAction $action, string $description, ?array $details = null): void
    {
        if ($this->activityLogger === null) {
            return;
        }

        try {
            $this->activityLogger->log($user, $action, $description, $details);
        } catch (\Throwable $e) {
            $this->logger?->warning('Error registrant auditoria successòria: ' . $e->getMessage());
        }
    }
}

