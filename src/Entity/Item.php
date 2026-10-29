<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AcquisitionType;
use App\Enum\ItemStatus;
use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'items')]
#[ORM\Index(columns: ['cui'], name: 'idx_items_cui')]
#[ORM\Index(columns: ['owner_id', 'status'], name: 'idx_items_owner_status')]
#[ORM\Index(columns: ['serial_number'], name: 'idx_items_serial')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_items_parent')]
#[ORM\Index(columns: ['category_id'], name: 'idx_items_category')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    /**
     * Codi Únic d'Identificació notarial (Ex: REG-2026-A4B7C9)
     */
    #[ORM\Column(type: 'string', length: 32, unique: true)]
    private string $cui;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Category $category;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(name: 'subcategory_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $subcategory = null;

    /**
     * Indica si aquest ítem és una col·lecció / lot que agrupa subítems
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isCollection = false;

    /**
     * Relació recursiva pare/fill per a col·leccions i subítems
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Item $parent = null;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private ?string $serialNumber = null;

    /**
     * Valor declarat en cèntims d'euro (ex: 150000 = 1.500,00 €)
     */
    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $declaredValueCents = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $acquisitionDate = null;

    #[ORM\Column(type: 'string', length: 30, enumType: AcquisitionType::class)]
    private AcquisitionType $acquisitionType = AcquisitionType::PURCHASE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $acquisitionCircumstances = null;

    /**
     * Atributs personalitzats segons categoria en JSON
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $customAttributes = null;

    /**
     * Dades deduïdes/extretes per l'assistent d'IA SGLang (Qwen VL)
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiExtractedData = null;

    #[ORM\Column(type: 'string', length: 30, enumType: ItemStatus::class, options: ['default' => 'active'])]
    private ItemStatus $status = ItemStatus::ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, ItemDocument>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: ItemDocument::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $documents;

    /**
     * @var Collection<int, BlockchainReceipt>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: BlockchainReceipt::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['anchoredAt' => 'DESC'])]
    private Collection $blockchainReceipts;

    /**
     * @var Collection<int, Certificate>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: Certificate::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $certificates;

    public function __construct(User $owner, string $cui, string $name, Category $category)
    {
        $this->uuid = Uuid::v7();
        $this->owner = $owner;
        $this->cui = $cui;
        $this->name = $name;
        $this->category = $category;
        $this->children = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->blockchainReceipts = new ArrayCollection();
        $this->certificates = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getCui(): string
    {
        return $this->cui;
    }

    public function setCui(string $cui): self
    {
        $this->cui = $cui;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function setCategory(Category $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getSubcategory(): ?Category
    {
        return $this->subcategory;
    }

    public function setSubcategory(?Category $subcategory): self
    {
        $this->subcategory = $subcategory;
        return $this;
    }

    public function isCollection(): bool
    {
        return $this->isCollection;
    }

    public function setIsCollection(bool $isCollection): self
    {
        $this->isCollection = $isCollection;
        return $this;
    }

    public function getParent(): ?Item
    {
        return $this->parent;
    }

    public function setParent(?Item $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(Item $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Item $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function setBrand(?string $brand): self
    {
        $this->brand = $brand;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): self
    {
        $this->model = $model;
        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): self
    {
        $this->serialNumber = $serialNumber;
        return $this;
    }

    public function getDeclaredValueCents(): ?int
    {
        return $this->declaredValueCents;
    }

    public function setDeclaredValueCents(?int $declaredValueCents): self
    {
        $this->declaredValueCents = $declaredValueCents;
        return $this;
    }

    public function getDeclaredValueEuros(): ?float
    {
        return $this->declaredValueCents !== null ? $this->declaredValueCents / 100.0 : null;
    }

    public function setDeclaredValueEuros(?float $euros): self
    {
        $this->declaredValueCents = $euros !== null ? (int) round($euros * 100) : null;
        return $this;
    }

    public function getAcquisitionDate(): ?\DateTimeImmutable
    {
        return $this->acquisitionDate;
    }

    public function setAcquisitionDate(?\DateTimeImmutable $acquisitionDate): self
    {
        $this->acquisitionDate = $acquisitionDate;
        return $this;
    }

    public function getAcquisitionType(): AcquisitionType
    {
        return $this->acquisitionType;
    }

    public function setAcquisitionType(AcquisitionType $acquisitionType): self
    {
        $this->acquisitionType = $acquisitionType;
        return $this;
    }

    public function getAcquisitionCircumstances(): ?string
    {
        return $this->acquisitionCircumstances;
    }

    public function setAcquisitionCircumstances(?string $acquisitionCircumstances): self
    {
        $this->acquisitionCircumstances = $acquisitionCircumstances;
        return $this;
    }

    /**
     * Àlies de conveniència per a circumstàncies/descripció
     */
    public function getDescription(): ?string
    {
        return $this->acquisitionCircumstances;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCustomAttributes(): ?array
    {
        return $this->customAttributes;
    }

    /**
     * @param array<string, mixed>|null $customAttributes
     */
    public function setCustomAttributes(?array $customAttributes): self
    {
        $this->customAttributes = $customAttributes;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAiExtractedData(): ?array
    {
        return $this->aiExtractedData;
    }

    /**
     * @param array<string, mixed>|null $aiExtractedData
     */
    public function setAiExtractedData(?array $aiExtractedData): self
    {
        $this->aiExtractedData = $aiExtractedData;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getStatus(): ItemStatus
    {
        return $this->status;
    }

    public function setStatus(ItemStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, ItemDocument>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(ItemDocument $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setItem($this);
        }
        return $this;
    }

    public function removeDocument(ItemDocument $document): self
    {
        if ($this->documents->removeElement($document)) {
            // orphanRemoval s'encarregarà de suprimir-lo si escau
        }
        return $this;
    }

    /**
     * Retorna el document de fotografia principal si existeix
     */
    public function getMainPhoto(): ?ItemDocument
    {
        foreach ($this->documents as $doc) {
            if ($doc->getType() === \App\Enum\DocumentType::PHOTO_PRIMARY) {
                return $doc;
            }
        }
        // Si no té photo_primary, retornem la primera foto de detall
        foreach ($this->documents as $doc) {
            if ($doc->getType() === \App\Enum\DocumentType::PHOTO_DETAIL) {
                return $doc;
            }
        }
        return null;
    }

    /**
     * Retorna exclusivament la col·lecció de fotografies (principal i de detall)
     *
     * @return Collection<int, ItemDocument>
     */
    public function getPhotos(): Collection
    {
        return $this->documents->filter(fn(ItemDocument $doc) => $doc->getType()->isPhoto());
    }

    /**
     * Retorna exclusivament la col·lecció de proves documentals (factures, garanties, denúncies, etc.)
     *
     * @return Collection<int, ItemDocument>
     */
    public function getEvidenceDocuments(): Collection
    {
        return $this->documents->filter(fn(ItemDocument $doc) => $doc->getType()->isEvidence());
    }

    /**
     * Àlies de conveniència per a getEvidenceDocuments()
     *
     * @return Collection<int, ItemDocument>
     */
    public function getEvidences(): Collection
    {
        return $this->getEvidenceDocuments();
    }

    /**
     * @return Collection<int, BlockchainReceipt>
     */
    public function getBlockchainReceipts(): Collection
    {
        return $this->blockchainReceipts;
    }

    public function addBlockchainReceipt(BlockchainReceipt $receipt): static
    {
        if (!$this->blockchainReceipts->contains($receipt)) {
            $this->blockchainReceipts->add($receipt);
            $receipt->setItem($this);
        }

        return $this;
    }

    public function getLatestBlockchainReceipt(): ?BlockchainReceipt
    {
        return $this->blockchainReceipts->first() ?: null;
    }

    public function isBlockchainAnchored(): bool
    {
        return !$this->blockchainReceipts->isEmpty();
    }

    /**
     * @return Collection<int, Certificate>
     */
    public function getCertificates(): Collection
    {
        return $this->certificates;
    }

    public function addCertificate(Certificate $certificate): static
    {
        if (!$this->certificates->contains($certificate)) {
            $this->certificates->add($certificate);
            $certificate->setItem($this);
        }

        return $this;
    }

    public function getLatestCertificate(?\App\Enum\CertificateMode $mode = null): ?Certificate
    {
        if ($mode === null) {
            return $this->certificates->first() ?: null;
        }

        foreach ($this->certificates as $cert) {
            if ($cert->getMode() === $mode) {
                return $cert;
            }
        }

        return null;
    }
}

