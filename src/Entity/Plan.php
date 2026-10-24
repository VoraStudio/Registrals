<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanRepository::class)]
#[ORM\Table(name: 'plans')]
class Plan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $code;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $priceCents = 0;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'yearly'])]
    private string $billingPeriod = 'yearly';

    #[ORM\Column(type: 'integer', options: ['default' => 20])]
    private int $maxActiveItems = 20;

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private int $maxLifetimeItems = 50;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $monthlyCertificatesQuota = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $allowOfficialCertificates = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $aiAssistanceQuota = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $transfersIncluded = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 200])]
    private int $extraTransferPriceCents = 200;

    #[ORM\Column(type: 'integer', options: ['default' => 4])]
    private int $maxDocumentsPerItem = 4;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $maxPhotosPerItem = 1;

    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $maxEvidencesPerItem = 1;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $code, string $name, int $priceCents = 0)
    {
        $this->code = $code;
        $this->name = $name;
        $this->priceCents = $priceCents;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): static
    {
        $this->priceCents = $priceCents;
        return $this;
    }

    public function getBillingPeriod(): string
    {
        return $this->billingPeriod;
    }

    public function setBillingPeriod(string $billingPeriod): static
    {
        $this->billingPeriod = $billingPeriod;
        return $this;
    }

    public function getMaxActiveItems(): int
    {
        return $this->maxActiveItems;
    }

    public function setMaxActiveItems(int $maxActiveItems): static
    {
        $this->maxActiveItems = $maxActiveItems;
        return $this;
    }

    public function getMaxLifetimeItems(): int
    {
        return $this->maxLifetimeItems;
    }

    public function setMaxLifetimeItems(int $maxLifetimeItems): static
    {
        $this->maxLifetimeItems = $maxLifetimeItems;
        return $this;
    }

    public function getMonthlyCertificatesQuota(): int
    {
        return $this->monthlyCertificatesQuota;
    }

    public function setMonthlyCertificatesQuota(int $monthlyCertificatesQuota): static
    {
        $this->monthlyCertificatesQuota = $monthlyCertificatesQuota;
        return $this;
    }

    public function isAllowOfficialCertificates(): bool
    {
        return $this->allowOfficialCertificates;
    }

    public function setAllowOfficialCertificates(bool $allowOfficialCertificates): static
    {
        $this->allowOfficialCertificates = $allowOfficialCertificates;
        return $this;
    }

    public function getAiAssistanceQuota(): int
    {
        return $this->aiAssistanceQuota;
    }

    public function setAiAssistanceQuota(int $aiAssistanceQuota): static
    {
        $this->aiAssistanceQuota = $aiAssistanceQuota;
        return $this;
    }

    public function getTransfersIncluded(): int
    {
        return $this->transfersIncluded;
    }

    public function setTransfersIncluded(int $transfersIncluded): static
    {
        $this->transfersIncluded = $transfersIncluded;
        return $this;
    }

    public function getExtraTransferPriceCents(): int
    {
        return $this->extraTransferPriceCents;
    }

    public function setExtraTransferPriceCents(int $extraTransferPriceCents): static
    {
        $this->extraTransferPriceCents = $extraTransferPriceCents;
        return $this;
    }

    public function getMaxDocumentsPerItem(): int
    {
        return $this->maxDocumentsPerItem;
    }

    public function setMaxDocumentsPerItem(int $maxDocumentsPerItem): static
    {
        $this->maxDocumentsPerItem = $maxDocumentsPerItem;
        return $this;
    }

    public function getMaxPhotosPerItem(): int
    {
        return $this->maxPhotosPerItem;
    }

    public function setMaxPhotosPerItem(int $maxPhotosPerItem): static
    {
        $this->maxPhotosPerItem = $maxPhotosPerItem;
        return $this;
    }

    public function getMaxEvidencesPerItem(): int
    {
        return $this->maxEvidencesPerItem;
    }

    public function setMaxEvidencesPerItem(int $maxEvidencesPerItem): static
    {
        $this->maxEvidencesPerItem = $maxEvidencesPerItem;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
