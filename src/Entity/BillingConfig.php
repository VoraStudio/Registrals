<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BillingConfigRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Configuració fiscal de l'emissor (Registrals / Titular de la plataforma).
 * Governa les dades legals, les sèries de facturació i els tipus impositius vigents.
 */
#[ORM\Entity(repositoryClass: BillingConfigRepository::class)]
#[ORM\Table(name: 'billing_configs')]
class BillingConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $legalName;

    #[ORM\Column(type: Types::STRING, length: 30)]
    private string $taxId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $address;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $postalCode;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $city;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $province;

    #[ORM\Column(type: Types::STRING, length: 2, options: ['default' => 'ES'])]
    private string $countryCode = 'ES';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $mercantileRegistry = null;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $billingEmail;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '21.00'])]
    private string $defaultVatRate = '21.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '0.00'])]
    private string $defaultIrpfRate = '0.00';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'REG-'])]
    private string $seriesComplete = 'REG-';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'FS-'])]
    private string $seriesSimplified = 'FS-';

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'R-'])]
    private string $seriesRectification = 'R-';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $nextNumberComplete = 1;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $nextNumberSimplified = 1;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 1])]
    private int $nextNumberRectification = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $footerLegalText = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $legalName,
        string $taxId,
        string $address,
        string $postalCode,
        string $city,
        string $province,
        string $billingEmail,
        string $countryCode = 'ES'
    ) {
        $this->legalName = $legalName;
        $this->taxId = $taxId;
        $this->address = $address;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->province = $province;
        $this->billingEmail = $billingEmail;
        $this->countryCode = strtoupper($countryCode);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLegalName(): string
    {
        return $this->legalName;
    }

    public function setLegalName(string $legalName): static
    {
        $this->legalName = $legalName;
        return $this;
    }

    public function getTaxId(): string
    {
        return $this->taxId;
    }

    public function setTaxId(string $taxId): static
    {
        $this->taxId = strtoupper($taxId);
        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getProvince(): string
    {
        return $this->province;
    }

    public function setProvince(string $province): static
    {
        $this->province = $province;
        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): static
    {
        $this->countryCode = strtoupper($countryCode);
        return $this;
    }

    public function getMercantileRegistry(): ?string
    {
        return $this->mercantileRegistry;
    }

    public function setMercantileRegistry(?string $mercantileRegistry): static
    {
        $this->mercantileRegistry = $mercantileRegistry;
        return $this;
    }

    public function getBillingEmail(): string
    {
        return $this->billingEmail;
    }

    public function setBillingEmail(string $billingEmail): static
    {
        $this->billingEmail = $billingEmail;
        return $this;
    }

    public function getDefaultVatRate(): string
    {
        return $this->defaultVatRate;
    }

    public function setDefaultVatRate(string $defaultVatRate): static
    {
        $this->defaultVatRate = $defaultVatRate;
        return $this;
    }

    public function getDefaultIrpfRate(): string
    {
        return $this->defaultIrpfRate;
    }

    public function setDefaultIrpfRate(string $defaultIrpfRate): static
    {
        $this->defaultIrpfRate = $defaultIrpfRate;
        return $this;
    }

    public function getSeriesComplete(): string
    {
        return $this->seriesComplete;
    }

    public function setSeriesComplete(string $seriesComplete): static
    {
        $this->seriesComplete = $seriesComplete;
        return $this;
    }

    public function getSeriesSimplified(): string
    {
        return $this->seriesSimplified;
    }

    public function setSeriesSimplified(string $seriesSimplified): static
    {
        $this->seriesSimplified = $seriesSimplified;
        return $this;
    }

    public function getSeriesRectification(): string
    {
        return $this->seriesRectification;
    }

    public function setSeriesRectification(string $seriesRectification): static
    {
        $this->seriesRectification = $seriesRectification;
        return $this;
    }

    public function getNextNumberComplete(): int
    {
        return $this->nextNumberComplete;
    }

    public function setNextNumberComplete(int $nextNumberComplete): static
    {
        $this->nextNumberComplete = $nextNumberComplete;
        return $this;
    }

    public function getNextNumberSimplified(): int
    {
        return $this->nextNumberSimplified;
    }

    public function setNextNumberSimplified(int $nextNumberSimplified): static
    {
        $this->nextNumberSimplified = $nextNumberSimplified;
        return $this;
    }

    public function getNextNumberRectification(): int
    {
        return $this->nextNumberRectification;
    }

    public function setNextNumberRectification(int $nextNumberRectification): static
    {
        $this->nextNumberRectification = $nextNumberRectification;
        return $this;
    }

    public function getFooterLegalText(): ?string
    {
        return $this->footerLegalText;
    }

    public function setFooterLegalText(?string $footerLegalText): static
    {
        $this->footerLegalText = $footerLegalText;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Genera l'instantània immutable de l'emissor per incrustar a la factura.
     *
     * @return array<string, mixed>
     */
    public function toIssuerSnapshot(): array
    {
        return [
            'legal_name' => $this->legalName,
            'tax_id' => $this->taxId,
            'address' => $this->address,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'province' => $this->province,
            'country_code' => $this->countryCode,
            'mercantile_registry' => $this->mercantileRegistry,
            'billing_email' => $this->billingEmail,
            'footer_legal_text' => $this->footerLegalText,
        ];
    }
}

