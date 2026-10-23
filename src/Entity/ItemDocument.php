<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DocumentType;
use App\Repository\ItemDocumentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ItemDocumentRepository::class)]
#[ORM\Table(name: 'item_documents')]
#[ORM\Index(columns: ['item_id', 'type'], name: 'idx_doc_item_type')]
#[ORM\Index(columns: ['sha256_hash'], name: 'idx_doc_sha256')]
class ItemDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\Column(type: 'string', length: 35, enumType: DocumentType::class)]
    private DocumentType $type;

    /**
     * Ruta relativa dins del sistema de custòdia privat Flysystem (mai a l'arrel web pública)
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $filePath;

    #[ORM\Column(type: 'string', length: 150)]
    private string $originalFilename;

    #[ORM\Column(type: 'string', length: 100)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private int $fileSizeBytes;

    /**
     * Hash criptogràfic SHA-256 calculat en el moment de la càrrega
     */
    #[ORM\Column(type: 'string', length: 64)]
    private string $sha256Hash;

    /**
     * Metadades extretes per visió/OCR local (SGLang Qwen VL) o dades EXIF (GPS, Timestamp)
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiMetadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Item $item,
        DocumentType $type,
        string $filePath,
        string $originalFilename,
        string $mimeType,
        int $fileSizeBytes,
        string $sha256Hash
    ) {
        $this->uuid = Uuid::v7();
        $this->item = $item;
        $this->type = $type;
        $this->filePath = $filePath;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->fileSizeBytes = $fileSizeBytes;
        $this->sha256Hash = $sha256Hash;
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

    public function getItem(): Item
    {
        return $this->item;
    }

    public function setItem(Item $item): self
    {
        $this->item = $item;
        return $this;
    }

    public function getType(): DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSizeBytes(): int
    {
        return $this->fileSizeBytes;
    }

    public function setFileSizeBytes(int $fileSizeBytes): self
    {
        $this->fileSizeBytes = $fileSizeBytes;
        return $this;
    }

    public function getSha256Hash(): string
    {
        return $this->sha256Hash;
    }

    public function setSha256Hash(string $sha256Hash): self
    {
        $this->sha256Hash = $sha256Hash;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAiMetadata(): ?array
    {
        return $this->aiMetadata;
    }

    /**
     * @param array<string, mixed>|null $aiMetadata
     */
    public function setAiMetadata(?array $aiMetadata): self
    {
        $this->aiMetadata = $aiMetadata;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
