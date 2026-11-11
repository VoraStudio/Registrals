<?php

declare(strict_types=1);

namespace App\Service\Legacy;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Service\UserActivityLogger;
use App\Entity\Certificate;
use App\Entity\LegacyContact;
use App\Entity\User;
use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Enum\LegacyContactStatus;
use App\Infrastructure\Adapter\Signature\SignaturitTimestampInterface;
use App\Repository\CertificateRepository;
use App\Repository\ItemRepository;
use App\Repository\LegacyContactRepository;
use App\Service\Certificate\CertificateQrGeneratorService;
use App\Service\Certificate\PdfRendererService;
use App\Service\ItemFingerprintCalculator;
use App\Service\Security\PrivacyMasker;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Twig\Environment;

/**
 * Orquestra l'emissió, maquetació PDF multipàgina, segellat eIDAS i validació d'integritat
 * dels Certificats d'Inventari Global de Llegat Digital.
 */
class LegacyCertificateIssuerService
{
    public function __construct(
        private readonly LegacyContactRepository $legacyContactRepository,
        private readonly ItemRepository $itemRepository,
        private readonly CertificateRepository $certificateRepository,
        private readonly ItemFingerprintCalculator $fingerprintCalculator,
        private readonly CertificateQrGeneratorService $qrGenerator,
        private readonly PdfRendererService $pdfRenderer,
        private readonly EntityManagerInterface $entityManager,
        private readonly FilesystemOperator $itemDocumentStorage,
        private readonly Environment $twig,
        private readonly KernelInterface $kernel,
        private readonly ?SignaturitTimestampInterface $signaturitClient = null,
        private readonly ?UrlGeneratorInterface $urlGenerator = null,
        private readonly ?UserActivityLogger $activityLogger = null,
        private readonly string $defaultBaseUrl = 'https://registrals.cat',
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array{
     *     canIssue: bool,
     *     reason: ?string,
     *     acceptedContact: ?LegacyContact,
     *     activeItemsCount: int,
     *     latestValidCert: ?Certificate,
     *     isSnapshotStale: bool,
     *     alreadyUpToDate: bool
     * }
     */
    public function evaluateEmissionEligibility(User $owner): array
    {
        $plan = $owner->getPlan();
        $acceptedContacts = $this->legacyContactRepository->findAcceptedByOwner($owner);
        $acceptedContact = $acceptedContacts[0] ?? null;
        $activeItems = $this->itemRepository->findActiveForLegacyInventory($owner);
        $activeItemsCount = count($activeItems);

        $validCerts = $this->certificateRepository->findValidLegacyInventoryByOwner($owner);
        $latestValidCert = $validCerts[0] ?? null;
        $isSnapshotStale = $latestValidCert instanceof Certificate
            ? !$this->isLegacyInventorySnapshotCurrent($latestValidCert)
            : false;

        $base = [
            'acceptedContact' => $acceptedContact,
            'activeItemsCount' => $activeItemsCount,
            'latestValidCert' => $latestValidCert,
            'isSnapshotStale' => $isSnapshotStale,
            'alreadyUpToDate' => false,
        ];

        $isAdmin = in_array('ROLE_ADMIN', $owner->getRoles(), true);

        if (!$isAdmin && ($plan === null || !$plan->isAllowLegacyDesignation() || !$plan->isAllowOfficialCertificates())) {
            return $base + ['canIssue' => false, 'reason' => 'plan_not_allowed'];
        }

        if (trim($owner->getFirstName()) === '' || trim($owner->getLastName()) === '') {
            return $base + ['canIssue' => false, 'reason' => 'missing_full_name'];
        }

        if (!$acceptedContact instanceof LegacyContact) {
            return $base + ['canIssue' => false, 'reason' => 'no_accepted_contact'];
        }

        if ($activeItemsCount === 0) {
            return $base + ['canIssue' => false, 'reason' => 'no_active_items'];
        }

        if ($latestValidCert instanceof Certificate && !$isSnapshotStale) {
            return [
                'canIssue' => true,
                'reason' => null,
                'acceptedContact' => $acceptedContact,
                'activeItemsCount' => $activeItemsCount,
                'latestValidCert' => $latestValidCert,
                'isSnapshotStale' => false,
                'alreadyUpToDate' => true,
            ];
        }

        if (!$isAdmin && $plan !== null) {
            $quota = $plan->getMonthlyCertificatesQuota();
            if ($quota !== -1 && $quota < 9999 && $owner->getCertificatesIssuedCount() >= $quota) {
                return $base + ['canIssue' => false, 'reason' => 'quota_reached'];
            }
        }

        return $base + ['canIssue' => true, 'reason' => null];
    }

    /**
     * Construeix la fotografia determinista dels béns actius del titular i el fingerprint agregat.
     *
     * @return array{
     *     items: list<array{
     *         cui: string,
     *         name: string,
     *         category: string,
     *         serialNumber: ?string,
     *         fingerprintSha256: string,
     *         polygonTxHash: ?string,
     *         polygonShortTxHash: ?string,
     *         polygonBlock: ?int
     *     }>,
     *     aggregateFingerprint: string
     * }
     */
    public function buildInventorySnapshot(User $owner, LegacyContact $contact): array
    {
        $items = $this->itemRepository->findActiveForLegacyInventory($owner);
        $snapshotRows = [];
        $fingerprintTokens = [];

        foreach ($items as $item) {
            $itemFingerprint = $this->fingerprintCalculator->calculateForItem($item);
            $receipt = $item->getLatestBlockchainReceipt();

            $snapshotRows[] = [
                'cui' => $item->getCui(),
                'name' => $item->getName(),
                'category' => $item->getCategory()?->getName() ?? 'Bé moble',
                'serialNumber' => $item->getSerialNumber(),
                'fingerprintSha256' => $itemFingerprint,
                'polygonTxHash' => $receipt?->getTxHash(),
                'polygonShortTxHash' => $receipt?->getShortTxHash(),
                'polygonBlock' => $receipt?->getBlockNumber(),
            ];

            $fingerprintTokens[] = $item->getCui() . ':' . $itemFingerprint;
        }

        sort($fingerprintTokens, SORT_STRING);

        $aggregatePayload = implode('|', [
            $owner->getUuid()->toRfc4122(),
            $contact->getUuid()->toRfc4122(),
            implode(',', $fingerprintTokens),
        ]);

        return [
            'items' => $snapshotRows,
            'aggregateFingerprint' => hash('sha256', $aggregatePayload),
        ];
    }

    public function isLegacyInventorySnapshotCurrent(Certificate $certificate): bool
    {
        if (!$certificate->isLegacyInventory() || !$certificate->isValid()) {
            return false;
        }

        $contact = $certificate->getLegacyContact();
        if (!$contact instanceof LegacyContact || $contact->getStatus() !== LegacyContactStatus::ACCEPTED) {
            return false;
        }

        $snapshot = $this->buildInventorySnapshot($certificate->getIssuedBy(), $contact);
        if ($snapshot['items'] === []) {
            return false;
        }

        return hash_equals($certificate->getItemFingerprintAtIssue(), $snapshot['aggregateFingerprint']);
    }

    public function issueLegacyInventoryCertificate(
        User $owner,
        ?LegacyContact $explicitContact = null,
        ?string $clientIp = null
    ): Certificate {
        $plan = $owner->getPlan();
        $isAdmin = in_array('ROLE_ADMIN', $owner->getRoles(), true);

        if (!$isAdmin && ($plan === null || !$plan->isAllowLegacyDesignation() || !$plan->isAllowOfficialCertificates())) {
            throw new DomainException('Aquesta funcionalitat requereix un pla actiu amb continuïtat patrimonial (Pla Plus o superior).');
        }

        if (trim($owner->getFirstName()) === '' || trim($owner->getLastName()) === '') {
            throw new DomainException('Has de desar el teu nom i cognoms reals al perfil abans d\'emetre el Certificat d\'Inventari Global de Llegat.');
        }

        if ($explicitContact !== null) {
            if ($explicitContact->getOwner()->getId() !== $owner->getId()) {
                throw new DomainException('La persona de confiança seleccionada no pertany al teu compte.');
            }
            if ($explicitContact->getStatus() !== LegacyContactStatus::ACCEPTED) {
                throw new DomainException('La Persona de Confiança ha d\'haver acceptat la designació abans d\'emetre el certificat.');
            }
            $contact = $explicitContact;
        } else {
            $acceptedContacts = $this->legacyContactRepository->findAcceptedByOwner($owner);
            $contact = $acceptedContacts[0] ?? null;
            if (!$contact instanceof LegacyContact) {
                throw new DomainException('Necessites tenir una Persona de Confiança acceptada abans d\'emetre el Certificat d\'Inventari Global de Llegat.');
            }
        }

        $snapshot = $this->buildInventorySnapshot($owner, $contact);
        if ($snapshot['items'] === []) {
            throw new DomainException('Has de tenir com a mínim un bé actiu custodiat per poder emetre el Certificat d\'Inventari Global de Llegat.');
        }

        $aggregateFingerprint = $snapshot['aggregateFingerprint'];
        $existingValid = $this->certificateRepository->findValidLegacyInventoryByOwner($owner);

        foreach ($existingValid as $existing) {
            if (
                $existing->getLegacyContact()?->getId() === $contact->getId()
                && hash_equals($existing->getItemFingerprintAtIssue(), $aggregateFingerprint)
            ) {
                $this->logger?->info('Reutilitzat certificat vigent d\'Inventari Global de Llegat sense canvis', [
                    'certificate_number' => $existing->getCertificateNumber(),
                    'owner_uuid' => $owner->getUuid()->toRfc4122(),
                ]);

                return $existing;
            }
        }

        if (!$isAdmin && $plan !== null) {
            $quota = $plan->getMonthlyCertificatesQuota();
            if ($quota !== -1 && $quota < 9999 && $owner->getCertificatesIssuedCount() >= $quota) {
                throw new DomainException(sprintf('Has assolit el límit mensual de %d certificats oficials del teu pla.', $quota));
            }
        }

        foreach ($existingValid as $previous) {
            $previous->setStatus(CertificateStatus::SUPERSEDED);
        }

        $certificateNumber = $this->certificateRepository->getNextCertificateNumber();
        $certificateUuid = Uuid::v7();
        $rawClaimToken = bin2hex(random_bytes(32));
        $claimTokenHash = hash('sha256', $rawClaimToken);

        $verifyUrl = $this->generateAbsoluteUrl(
            'app_verify_certificate',
            ['uuid' => $certificateUuid->toRfc4122()],
            '/verify/' . $certificateUuid->toRfc4122()
        );
        $claimUrl = $this->generateAbsoluteUrl(
            'app_legacy_claim_entry',
            ['token' => $rawClaimToken],
            '/herencia/reclamar/' . $rawClaimToken
        );

        $verifyQrDataUri = $this->qrGenerator->generateDataUri($verifyUrl);
        $claimQrDataUri = $this->qrGenerator->generateDataUri($claimUrl);
        $logoDataUri = $this->getLogoDataUri();

        $draftEntity = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: $certificateNumber,
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: '',
            pdfSha256: 'calculant...',
            itemFingerprintAtIssue: $aggregateFingerprint,
            status: CertificateStatus::VALID,
            uuid: $certificateUuid,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
            includedItemCuisSnapshot: $snapshot['items'],
            claimTokenHash: $claimTokenHash,
        );

        $html = $this->twig->render('certificate/legacy_pdf_template.html.twig', [
            'certificate' => $draftEntity,
            'owner' => $owner,
            'contact' => $contact,
            'maskedOwnerEmail' => PrivacyMasker::maskEmail($owner->getEmail()),
            'maskedContactEmail' => PrivacyMasker::maskEmail($contact->getEmail()),
            'snapshotItems' => $snapshot['items'],
            'verifyQrDataUri' => $verifyQrDataUri,
            'claimQrDataUri' => $claimQrDataUri,
            'verifyUrl' => $verifyUrl,
            'claimUrl' => $claimUrl,
            'logoDataUri' => $logoDataUri,
        ]);

        $pdfBinary = $this->pdfRenderer->renderHtmlToPdf($html);
        $pdfSha256 = hash('sha256', $pdfBinary);

        $year = (new \DateTimeImmutable())->format('Y');
        $storagePath = sprintf('certificates/%s/LEGAT_%s.pdf', $year, $certificateNumber);
        $this->itemDocumentStorage->write($storagePath, $pdfBinary);

        $certificate = new Certificate(
            item: null,
            issuedBy: $owner,
            certificateNumber: $certificateNumber,
            mode: CertificateMode::OFFICIAL,
            pdfFilePath: $storagePath,
            pdfSha256: $pdfSha256,
            itemFingerprintAtIssue: $aggregateFingerprint,
            status: CertificateStatus::VALID,
            uuid: $certificateUuid,
            scope: CertificateScope::LEGACY_INVENTORY,
            legacyContact: $contact,
            includedItemCuisSnapshot: $snapshot['items'],
            claimTokenHash: $claimTokenHash,
        );

        if ($this->signaturitClient !== null) {
            $eidasDto = $this->signaturitClient->timestampHash($pdfSha256);
            $certificate->setEidasSignaturitId($eidasDto->tokenId);
            $certificate->setEidasTimestamp($eidasDto->timestamp);
        }

        $owner->setCertificatesIssuedCount($owner->getCertificatesIssuedCount() + 1);

        $this->entityManager->persist($certificate);
        $this->entityManager->flush();

        if ($this->activityLogger !== null) {
            try {
                $this->activityLogger->log(
                    user: $owner,
                    action: UserActivityAction::LEGACY_INVENTORY_CERT_ISSUED,
                    description: sprintf(
                        'Emissió de Certificat d\'Inventari Global de Llegat %s (%d béns, persona de confiança: %s)',
                        $certificateNumber,
                        count($snapshot['items']),
                        $contact->getFullName()
                    ),
                    details: [
                        'certificate_uuid' => $certificateUuid->toRfc4122(),
                        'certificate_number' => $certificateNumber,
                        'legacy_contact_uuid' => $contact->getUuid()->toRfc4122(),
                        'items_count' => count($snapshot['items']),
                        'aggregate_fingerprint' => $aggregateFingerprint,
                        'ip' => $clientIp,
                    ]
                );
            } catch (\Throwable $e) {
                $this->logger?->warning('Error registrant auditoria d\'emissió de certificat de llegat: ' . $e->getMessage());
            }
        }

        return $certificate;
    }

    /**
     * Resol i valida l'estat d'un QR dedicat d'obertura successòria (/herencia/reclamar/{token}).
     *
     * @return array{certificate: ?Certificate, state: string, isCurrentSnapshot: bool}
     */
    public function resolveClaimByRawToken(string $rawToken): array
    {
        if ($rawToken === '' || strlen($rawToken) !== 64 || !ctype_xdigit($rawToken)) {
            return ['certificate' => null, 'state' => 'invalid', 'isCurrentSnapshot' => false];
        }

        $certificate = $this->certificateRepository->findByClaimTokenHash(hash('sha256', $rawToken));
        if (!$certificate instanceof Certificate || !$certificate->isLegacyInventory()) {
            return ['certificate' => null, 'state' => 'invalid', 'isCurrentSnapshot' => false];
        }

        if ($certificate->getStatus() === CertificateStatus::REVOKED) {
            return ['certificate' => $certificate, 'state' => 'revoked', 'isCurrentSnapshot' => false];
        }

        if ($certificate->getStatus() === CertificateStatus::SUPERSEDED) {
            return ['certificate' => $certificate, 'state' => 'superseded', 'isCurrentSnapshot' => false];
        }

        $contact = $certificate->getLegacyContact();
        if (!$contact instanceof LegacyContact || $contact->getStatus() !== LegacyContactStatus::ACCEPTED) {
            return ['certificate' => $certificate, 'state' => 'revoked', 'isCurrentSnapshot' => false];
        }

        return [
            'certificate' => $certificate,
            'state' => 'valid',
            'isCurrentSnapshot' => $this->isLegacyInventorySnapshotCurrent($certificate),
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function generateAbsoluteUrl(string $route, array $params, string $fallbackPath): string
    {
        if ($this->urlGenerator !== null) {
            try {
                return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
            } catch (\Throwable) {
                // Fallback determinista quan s'executa en tests unitaris aïllats sense router
            }
        }

        return rtrim($this->defaultBaseUrl, '/') . $fallbackPath;
    }

    private function getLogoDataUri(): ?string
    {
        $logoPath = $this->kernel->getProjectDir() . '/web/img/logos/Isotip/1-transparent.svg';
        if (!file_exists($logoPath)) {
            return null;
        }

        $svgContent = file_get_contents($logoPath);
        if ($svgContent === false) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svgContent);
    }
}
