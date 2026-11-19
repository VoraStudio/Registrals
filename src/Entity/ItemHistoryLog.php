<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Registre immutable de versions d'un bé custodiat (domini Registrals).
 * Cada modificació de dades, fotografies o proves documentals genera una nova versió (v1, v2, ...)
 * amb el seu Master Fingerprint SHA-256 associat.
 */
#[ORM\Entity]
#[ORM\Table(name: 'item_history_logs')]
#[ORM\Index(columns: ['item_id', 'version'], name: 'idx_item_history_item_version')]
#[ORM\Index(columns: ['item_id', 'created_at'], name: 'idx_item_history_item_created')]
class ItemHistoryLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'historyLogs')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'actor_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(type: 'integer')]
    private int $version;

    #[ORM\Column(type: 'string', length: 40)]
    private string $eventType;

    #[ORM\Column(type: 'string', length: 255)]
    private string $summary;

    #[ORM\Column(type: 'string', length: 64)]
    private string $masterSha256;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        Item $item,
        int $version,
        string $eventType,
        string $summary,
        string $masterSha256,
        ?User $actor = null,
        ?array $metadata = null,
    ) {
        $this->item = $item;
        $this->version = max(1, $version);
        $this->eventType = $eventType;
        $this->summary = $summary;
        $this->masterSha256 = $masterSha256;
        $this->actor = $actor;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getActor(): ?User
    {
        return $this->actor;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getEventLabel(): string
    {
        return match ($this->eventType) {
            'created' => 'Alta inicial',
            'document_added' => 'Document afegit',
            'document_replaced' => 'Document substituït',
            'document_deleted' => 'Document eliminat',
            'primary_photo_changed' => 'Foto principal actualitzada',
            default => 'Actualització del bé',
        };
    }

    public function getSummary(): string
    {
        return $this->summary;
    }

    public function getMasterSha256(): string
    {
        return $this->masterSha256;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

