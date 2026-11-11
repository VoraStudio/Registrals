<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CertificateMode;
use App\Enum\CertificateScope;
use App\Enum\CertificateStatus;
use App\Repository\CertificateRepository;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CertificateRepository::class)]
#[ORM\Table(name: 'certificates')]
#[ORM\Index(columns: ['uuid'], name: 'idx_cert_uuid')]
#[ORM\Index(columns: ['certificate_number'], name: 'idx_cert_number')]
#[ORM\Index(columns: ['item_id', 'status'], name: 'idx_cert_item_status')]
#[ORM\Index(columns: ['pdf_sha256'], name: 'idx_cert_pdf_sha256')]
#[ORM\Index(columns: ['issued_by_user_id', 'scope', 'status'], name: 'idx_cert_issued_scope')]
class Certificate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Clau de resolució pública per al codi QR (/verify/{uuid})
     */
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'certificates')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Item $item = null;

    #[ORM\Column(type: 'string', length: 30, enumType: CertificateScope::class, options: ['default' => 'item'])]
    private CertificateScope $scope = CertificateScope::ITEM;

    #[ORM\ManyToOne(targetEntity: LegacyContact::class)]
    #[ORM\JoinColumn(name: 'legacy_contact_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?LegacyContact $legacyContact = null;

    /**
     * Fotografia immutable dels béns inclosos en un certificat d'Inventari Global de Llegat.
     *
     * @var list<array<string, mixed>>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $includedItemCuisSnapshot = null;

    #[ORM\Column(type: 'string', length: 64, unique: true, nullable: true)]
    private ?string $claimTokenHash = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'issued_by_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $issuedBy;

    /**
     * Número correlatiu notarial oficial (ex: CERT-2026-000491)
     */
    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $certificateNumber;

    #[ORM\Column(type: 'string', length: 20, enumType: CertificateMode::class)]
    private CertificateMode $mode;

    #[ORM\Column(type: 'string', length: 20, enumType: CertificateStatus::class, options: ['default' => 'valid'])]
    private CertificateStatus $status = CertificateStatus::VALID;

    /**
     * Ruta relativa dins del sistema de custòdia protegit Flysystem
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $pdfFilePath;

    /**
     * Hash criptogràfic SHA-256 del document PDF generat
     */
    #[ORM\Column(type: 'string', length: 64)]
    private string $pdfSha256;

    /**
     * Fingerprint mestre de l'ítem en el moment exacte de l'emissió
     */
    #[ORM\Column(type: 'string', length: 64)]
    private string $itemFingerprintAtIssue;

    /**
     * Identificador de l'Audit Trail o Token eIDAS de Signaturit
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $eidasSignaturitId = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $eidasTimestamp = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /**
     * @param list<array<string, mixed>>|null $includedItemCuisSnapshot
     */
    public function __construct(
        ?Item $item,
        User $issuedBy,
        string $certificateNumber,
        CertificateMode $mode,
        string $pdfFilePath,
        string $pdfSha256,
        string $itemFingerprintAtIssue,
        CertificateStatus $status = CertificateStatus::VALID,
        ?Uuid $uuid = null,
        CertificateScope $scope = CertificateScope::ITEM,
        ?LegacyContact $legacyContact = null,
        ?array $includedItemCuisSnapshot = null,
        ?string $claimTokenHash = null,
    ) {
        if ($scope === CertificateScope::ITEM && $item === null) {
            throw new InvalidArgumentException('Un certificat d\'inventari individual requereix un bé associat.');
        }
        if ($scope === CertificateScope::LEGACY_INVENTORY) {
            if ($item !== null) {
                throw new InvalidArgumentException('Un certificat d\'inventari global de llegat no pot quedar vinculat a un únic bé.');
            }
            if ($legacyContact === null) {
                throw new InvalidArgumentException('Un certificat d\'inventari global de llegat requereix una persona de confiança designada.');
            }
        }

        $this->uuid = $uuid ?? Uuid::v7();
        $this->item = $item;
        $this->issuedBy = $issuedBy;
        $this->certificateNumber = $certificateNumber;
        $this->mode = $mode;
        $this->pdfFilePath = $pdfFilePath;
        $this->pdfSha256 = $pdfSha256;
        $this->itemFingerprintAtIssue = $itemFingerprintAtIssue;
        $this->status = $status;
        $this->scope = $scope;
        $this->legacyContact = $legacyContact;
        $this->includedItemCuisSnapshot = $includedItemCuisSnapshot;
        $this->claimTokenHash = $claimTokenHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function requireItem(): Item
    {
        if ($this->item === null) {
            throw new LogicException(sprintf('El certificat %s és d\'àmbit global de llegat i no té un únic bé associat.', $this->certificateNumber));
        }

        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        if ($this->scope === CertificateScope::ITEM && $item === null) {
            throw new InvalidArgumentException('No es pot desvincular el bé d\'un certificat d\'inventari individual.');
        }

        $this->item = $item;

        return $this;
    }

    public function getScope(): CertificateScope
    {
        return $this->scope;
    }

    public function isLegacyInventory(): bool
    {
        return $this->scope->isLegacyInventory();
    }

    public function getLegacyContact(): ?LegacyContact
    {
        return $this->legacyContact;
    }

    public function setLegacyContact(?LegacyContact $legacyContact): static
    {
        $this->legacyContact = $legacyContact;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getIncludedItemCuisSnapshot(): ?array
    {
        return $this->includedItemCuisSnapshot;
    }

    /**
     * @param list<array<string, mixed>>|null $includedItemCuisSnapshot
     */
    public function setIncludedItemCuisSnapshot(?array $includedItemCuisSnapshot): static
    {
        $this->includedItemCuisSnapshot = $includedItemCuisSnapshot;

        return $this;
    }

    public function getClaimTokenHash(): ?string
    {
        return $this->claimTokenHash;
    }

    public function setClaimTokenHash(?string $claimTokenHash): static
    {
        $this->claimTokenHash = $claimTokenHash;

        return $this;
    }

    public function getIssuedBy(): User
    {
        return $this->issuedBy;
    }

    public function setIssuedBy(User $issuedBy): static
    {
        $this->issuedBy = $issuedBy;

        return $this;
    }

    public function getCertificateNumber(): string
    {
        return $this->certificateNumber;
    }

    public function setCertificateNumber(string $certificateNumber): static
    {
        $this->certificateNumber = $certificateNumber;

        return $this;
    }

    public function getMode(): CertificateMode
    {
        return $this->mode;
    }

    public function setMode(CertificateMode $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getStatus(): CertificateStatus
    {
        return $this->status;
    }

    public function setStatus(CertificateStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->status->isValid();
    }

    public function getPdfFilePath(): string
    {
        return $this->pdfFilePath;
    }

    public function setPdfFilePath(string $pdfFilePath): static
    {
        $this->pdfFilePath = $pdfFilePath;

        return $this;
    }

    public function getPdfSha256(): string
    {
        return $this->pdfSha256;
    }

    public function setPdfSha256(string $pdfSha256): static
    {
        $this->pdfSha256 = $pdfSha256;

        return $this;
    }

    public function getItemFingerprintAtIssue(): string
    {
        return $this->itemFingerprintAtIssue;
    }

    public function setItemFingerprintAtIssue(string $itemFingerprintAtIssue): static
    {
        $this->itemFingerprintAtIssue = $itemFingerprintAtIssue;

        return $this;
    }

    public function getEidasSignaturitId(): ?string
    {
        return $this->eidasSignaturitId;
    }

    public function setEidasSignaturitId(?string $eidasSignaturitId): static
    {
        $this->eidasSignaturitId = $eidasSignaturitId;

        return $this;
    }

    public function getEidasTimestamp(): ?\DateTimeImmutable
    {
        return $this->eidasTimestamp;
    }

    public function setEidasTimestamp(?\DateTimeImmutable $eidasTimestamp): static
    {
        $this->eidasTimestamp = $eidasTimestamp;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
    }

    public function getVerificationUrl(string $baseUrl = 'https://registrals.cat'): string
    {
        return rtrim($baseUrl, '/') . '/verify/' . $this->uuid->toRfc4122();
    }
}
