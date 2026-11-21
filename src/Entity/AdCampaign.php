<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AdCampaignRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdCampaignRepository::class)]
#[ORM\Table(name: 'ad_campaigns')]
#[ORM\Index(name: 'idx_ad_active_dates', columns: ['is_active', 'starts_at', 'ends_at'])]
class AdCampaign
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 160)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(name: 'media_path', type: 'string', length: 255)]
    private string $mediaPath;

    #[ORM\Column(name: 'media_type', type: 'string', length: 20, options: ['default' => 'image'])]
    private string $mediaType = 'image';

    #[ORM\Column(name: 'media_path_es', type: 'string', length: 255, nullable: true)]
    private ?string $mediaPathEs = null;

    #[ORM\Column(name: 'media_type_es', type: 'string', length: 20, nullable: true)]
    private ?string $mediaTypeEs = null;

    #[ORM\Column(name: 'media_path_en', type: 'string', length: 255, nullable: true)]
    private ?string $mediaPathEn = null;

    #[ORM\Column(name: 'media_type_en', type: 'string', length: 20, nullable: true)]
    private ?string $mediaTypeEn = null;

    #[ORM\Column(name: 'duration_seconds', type: 'integer', options: ['default' => 8])]
    private int $durationSeconds = 8;

    #[ORM\Column(name: 'starts_at', type: 'datetime_immutable')]
    private DateTimeImmutable $startsAt;

    #[ORM\Column(name: 'ends_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $endsAt = null;

    #[ORM\Column(name: 'is_active', type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'sort_order', type: 'integer', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $title,
        string $description,
        string $mediaPath,
        string $mediaType = 'image',
        int $durationSeconds = 8,
        ?DateTimeImmutable $startsAt = null,
        ?DateTimeImmutable $endsAt = null,
        bool $isActive = true,
        int $sortOrder = 0
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->mediaPath = $mediaPath;
        $this->mediaType = $mediaType;
        $this->durationSeconds = max(2, $durationSeconds);
        $this->startsAt = $startsAt ?? new DateTimeImmutable();
        $this->endsAt = $endsAt;
        $this->isActive = $isActive;
        $this->sortOrder = $sortOrder;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMediaPath(): string
    {
        return $this->mediaPath;
    }

    public function setMediaPath(string $mediaPath): static
    {
        $this->mediaPath = $mediaPath;

        return $this;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function setMediaType(string $mediaType): static
    {
        $this->mediaType = $mediaType;

        return $this;
    }

    public function getMediaPathEs(): ?string
    {
        return $this->mediaPathEs;
    }

    public function setMediaPathEs(?string $mediaPathEs): static
    {
        $this->mediaPathEs = $mediaPathEs;

        return $this;
    }

    public function getMediaTypeEs(): ?string
    {
        return $this->mediaTypeEs;
    }

    public function setMediaTypeEs(?string $mediaTypeEs): static
    {
        $this->mediaTypeEs = $mediaTypeEs;

        return $this;
    }

    public function getMediaPathEn(): ?string
    {
        return $this->mediaPathEn;
    }

    public function setMediaPathEn(?string $mediaPathEn): static
    {
        $this->mediaPathEn = $mediaPathEn;

        return $this;
    }

    public function getMediaTypeEn(): ?string
    {
        return $this->mediaTypeEn;
    }

    public function setMediaTypeEn(?string $mediaTypeEn): static
    {
        $this->mediaTypeEn = $mediaTypeEn;

        return $this;
    }

    /**
     * Retorna la creativitat per a l'idioma demanat, amb fallback automàtic a CAT.
     *
     * @return array{path: string, type: string}
     */
    public function getMediaForLocale(?string $locale = 'ca'): array
    {
        $lang = strtolower(substr((string) $locale, 0, 2));
        if ($lang === 'es' && $this->mediaPathEs !== null && $this->mediaPathEs !== '') {
            return ['path' => $this->mediaPathEs, 'type' => $this->mediaTypeEs ?? 'image'];
        }
        if ($lang === 'en' && $this->mediaPathEn !== null && $this->mediaPathEn !== '') {
            return ['path' => $this->mediaPathEn, 'type' => $this->mediaTypeEn ?? 'image'];
        }

        return ['path' => $this->mediaPath, 'type' => $this->mediaType];
    }

    public function getDurationSeconds(): int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(int $durationSeconds): static
    {
        $this->durationSeconds = max(2, $durationSeconds);

        return $this;
    }

    public function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCurrentlyVisible(?DateTimeImmutable $now = null): bool
    {
        if (!$this->isActive) {
            return false;
        }
        $at = $now ?? new DateTimeImmutable();

        return $this->startsAt <= $at && ($this->endsAt === null || $this->endsAt >= $at);
    }
}

