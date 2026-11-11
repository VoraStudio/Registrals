<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SuccessionClaimStatus;
use App\Repository\SuccessionClaimRepository;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SuccessionClaimRepository::class)]
#[ORM\Table(name: 'succession_claims')]
#[ORM\Index(columns: ['status'], name: 'idx_claim_status')]
#[ORM\Index(columns: ['deceased_owner_id', 'status'], name: 'idx_claim_owner_status')]
#[ORM\Index(columns: ['claim_reference'], name: 'idx_claim_reference')]
class SuccessionClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $claimReference;

    #[ORM\ManyToOne(targetEntity: Certificate::class)]
    #[ORM\JoinColumn(name: 'source_certificate_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Certificate $sourceCertificate;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'deceased_owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $deceasedOwner;

    #[ORM\ManyToOne(targetEntity: LegacyContact::class)]
    #[ORM\JoinColumn(name: 'legacy_contact_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?LegacyContact $legacyContact = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'claimant_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $claimantUser = null;

    #[ORM\Column(type: 'string', length: 180)]
    private string $claimantName;

    #[ORM\Column(type: 'string', length: 180)]
    private string $claimantEmail;

    #[ORM\Column(type: 'string', length: 30)]
    private string $claimantTaxId;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $claimantEmailOtpVerifiedAt;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $openingFeePaidCents = 0;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: 'string', length: 30, enumType: SuccessionClaimStatus::class, options: ['default' => 'pending_payment'])]
    private SuccessionClaimStatus $status = SuccessionClaimStatus::PENDING_PAYMENT;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $ownerAlertSentAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $securityHoldUntil = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $deathCertificateStorageKey;

    #[ORM\Column(type: 'string', length: 64)]
    private string $deathCertificateSha256;

    #[ORM\Column(type: 'string', length: 255)]
    private string $successionTitleStorageKey;

    #[ORM\Column(type: 'string', length: 64)]
    private string $successionTitleSha256;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'pending'])]
    private string $aiScreeningStatus = 'pending';

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiExtractionJson = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $aiConfidenceScore = null;

    #[ORM\Column(type: 'string', length: 36, nullable: true)]
    private ?string $reviewedByUserUuid = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $reviewedByEmail = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reviewedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolutionNotes = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $executedTransferCount = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $claimReference,
        Certificate $sourceCertificate,
        User $deceasedOwner,
        string $claimantName,
        string $claimantEmail,
        string $claimantTaxId,
        \DateTimeImmutable $claimantEmailOtpVerifiedAt,
        string $deathCertificateStorageKey,
        string $deathCertificateSha256,
        string $successionTitleStorageKey,
        string $successionTitleSha256,
        ?LegacyContact $legacyContact = null,
        ?User $claimantUser = null,
        ?Uuid $uuid = null
    ) {
        if (!$sourceCertificate->isLegacyInventory()) {
            throw new InvalidArgumentException('Un expedient successori només es pot obrir sobre un certificat d\'Inventari Global de Llegat.');
        }

        $now = new \DateTimeImmutable();
        $this->uuid = $uuid ?? Uuid::v7();
        $this->claimReference = $claimReference;
        $this->sourceCertificate = $sourceCertificate;
        $this->deceasedOwner = $deceasedOwner;
        $this->legacyContact = $legacyContact ?? $sourceCertificate->getLegacyContact();
        $this->claimantUser = $claimantUser;
        $this->claimantName = trim($claimantName);
        $this->claimantEmail = mb_strtolower(trim($claimantEmail));
        $this->claimantTaxId = strtoupper(trim($claimantTaxId));
        $this->claimantEmailOtpVerifiedAt = $claimantEmailOtpVerifiedAt;
        $this->deathCertificateStorageKey = $deathCertificateStorageKey;
        $this->deathCertificateSha256 = $deathCertificateSha256;
        $this->successionTitleStorageKey = $successionTitleStorageKey;
        $this->successionTitleSha256 = $successionTitleSha256;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getClaimReference(): string
    {
        return $this->claimReference;
    }

    public function getSourceCertificate(): Certificate
    {
        return $this->sourceCertificate;
    }

    public function getDeceasedOwner(): User
    {
        return $this->deceasedOwner;
    }

    public function getLegacyContact(): ?LegacyContact
    {
        return $this->legacyContact;
    }

    public function getClaimantUser(): ?User
    {
        return $this->claimantUser;
    }

    public function setClaimantUser(?User $claimantUser): static
    {
        $this->claimantUser = $claimantUser;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getClaimantName(): string
    {
        return $this->claimantName;
    }

    public function getClaimantEmail(): string
    {
        return $this->claimantEmail;
    }

    public function getClaimantTaxId(): string
    {
        return $this->claimantTaxId;
    }

    public function getClaimantEmailOtpVerifiedAt(): \DateTimeImmutable
    {
        return $this->claimantEmailOtpVerifiedAt;
    }

    public function getOpeningFeePaidCents(): int
    {
        return $this->openingFeePaidCents;
    }

    public function setOpeningFeePaidCents(int $openingFeePaidCents): static
    {
        $this->openingFeePaidCents = $openingFeePaidCents;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getStatus(): SuccessionClaimStatus
    {
        return $this->status;
    }

    public function setStatus(SuccessionClaimStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getOwnerAlertSentAt(): ?\DateTimeImmutable
    {
        return $this->ownerAlertSentAt;
    }

    public function getSecurityHoldUntil(): ?\DateTimeImmutable
    {
        return $this->securityHoldUntil;
    }

    public function isSecurityHoldActive(?\DateTimeImmutable $reference = null): bool
    {
        if ($this->securityHoldUntil === null) {
            return false;
        }

        return $this->securityHoldUntil > ($reference ?? new \DateTimeImmutable());
    }

    public function activateOwnerNoticeWindow(int $paidCents, ?string $paymentIntentId, \DateTimeImmutable $holdUntil): static
    {
        $now = new \DateTimeImmutable();
        $this->openingFeePaidCents = $paidCents;
        $this->stripePaymentIntentId = $paymentIntentId;
        $this->ownerAlertSentAt = $now;
        $this->securityHoldUntil = $holdUntil;
        $this->status = SuccessionClaimStatus::OWNER_NOTICE_WINDOW;
        $this->updatedAt = $now;

        return $this;
    }

    public function markAiPrescreeningQueued(): static
    {
        $this->aiScreeningStatus = 'pending';
        $this->status = SuccessionClaimStatus::AI_PRESCREENING;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * @param array<string, mixed> $extractionJson
     */
    public function recordAiPrescreeningResult(array $extractionJson, ?float $confidenceScore = null): static
    {
        $this->aiScreeningStatus = 'completed';
        $this->aiExtractionJson = $extractionJson;
        $this->aiConfidenceScore = $confidenceScore;
        $this->status = SuccessionClaimStatus::UNDER_HUMAN_REVIEW;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function markAiPrescreeningFailed(): static
    {
        $this->aiScreeningStatus = 'failed';
        $this->status = SuccessionClaimStatus::UNDER_HUMAN_REVIEW;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function approve(User $reviewer, int $executedTransferCount, string $resolutionNotes): static
    {
        $now = new \DateTimeImmutable();
        $this->reviewedByUserUuid = $reviewer->getUuid()->toRfc4122();
        $this->reviewedByEmail = $reviewer->getEmail();
        $this->reviewedAt = $now;
        $this->executedTransferCount = $executedTransferCount;
        $this->resolutionNotes = trim($resolutionNotes);
        $this->status = SuccessionClaimStatus::APPROVED;
        $this->updatedAt = $now;

        return $this;
    }

    public function reject(User $reviewer, string $resolutionNotes): static
    {
        $now = new \DateTimeImmutable();
        $this->reviewedByUserUuid = $reviewer->getUuid()->toRfc4122();
        $this->reviewedByEmail = $reviewer->getEmail();
        $this->reviewedAt = $now;
        $this->resolutionNotes = trim($resolutionNotes);
        $this->status = SuccessionClaimStatus::REJECTED;
        $this->updatedAt = $now;

        return $this;
    }

    public function getDeathCertificateStorageKey(): string
    {
        return $this->deathCertificateStorageKey;
    }

    public function getDeathCertificateSha256(): string
    {
        return $this->deathCertificateSha256;
    }

    public function getSuccessionTitleStorageKey(): string
    {
        return $this->successionTitleStorageKey;
    }

    public function getSuccessionTitleSha256(): string
    {
        return $this->successionTitleSha256;
    }

    public function getAiScreeningStatus(): string
    {
        return $this->aiScreeningStatus;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAiExtractionJson(): ?array
    {
        return $this->aiExtractionJson;
    }

    public function getAiConfidenceScore(): ?float
    {
        return $this->aiConfidenceScore;
    }

    public function getReviewedByUserUuid(): ?string
    {
        return $this->reviewedByUserUuid;
    }

    public function getReviewedByEmail(): ?string
    {
        return $this->reviewedByEmail;
    }

    public function getReviewedAt(): ?\DateTimeImmutable
    {
        return $this->reviewedAt;
    }

    public function getResolutionNotes(): ?string
    {
        return $this->resolutionNotes;
    }

    public function getExecutedTransferCount(): int
    {
        return $this->executedTransferCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

