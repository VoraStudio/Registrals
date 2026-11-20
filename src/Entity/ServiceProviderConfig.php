<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ServiceProviderCategory;
use App\Repository\ServiceProviderConfigRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServiceProviderConfigRepository::class)]
#[ORM\Table(name: 'service_provider_configs')]
#[ORM\Index(name: 'idx_spc_category_dates', columns: ['category', 'valid_from', 'valid_to'])]
class ServiceProviderConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 30, enumType: ServiceProviderCategory::class)]
    private ServiceProviderCategory $category;

    #[ORM\Column(name: 'provider_code', type: 'string', length: 60)]
    private string $providerCode;

    #[ORM\Column(name: 'public_name', type: 'string', length: 120)]
    private string $publicName;

    #[ORM\Column(name: 'valid_from', type: 'datetime_immutable')]
    private DateTimeImmutable $validFrom;

    #[ORM\Column(name: 'valid_to', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $validTo = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function __construct(
        ServiceProviderCategory $category,
        string $providerCode,
        string $publicName,
        ?DateTimeImmutable $validFrom = null,
        ?DateTimeImmutable $validTo = null,
        ?string $notes = null
    ) {
        $this->category = $category;
        $this->providerCode = $providerCode;
        $this->publicName = $publicName;
        $this->validFrom = $validFrom ?? new DateTimeImmutable();
        $this->validTo = $validTo;
        $this->notes = $notes;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ServiceProviderCategory
    {
        return $this->category;
    }

    public function setCategory(ServiceProviderCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getProviderCode(): string
    {
        return $this->providerCode;
    }

    public function setProviderCode(string $providerCode): static
    {
        $this->providerCode = $providerCode;

        return $this;
    }

    public function getPublicName(): string
    {
        return $this->publicName;
    }

    public function setPublicName(string $publicName): static
    {
        $this->publicName = $publicName;

        return $this;
    }

    public function getValidFrom(): DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function setValidFrom(DateTimeImmutable $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidTo(): ?DateTimeImmutable
    {
        return $this->validTo;
    }

    public function setValidTo(?DateTimeImmutable $validTo): static
    {
        $this->validTo = $validTo;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActiveAt(?DateTimeImmutable $at = null): bool
    {
        $target = $at ?? new DateTimeImmutable();

        return $this->validFrom <= $target && ($this->validTo === null || $this->validTo >= $target);
    }
}

