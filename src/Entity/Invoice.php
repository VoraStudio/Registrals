<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InvoiceStatus;
use App\Enum\InvoiceType;
use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Factura oficial autònoma emesa per la plataforma Registrals.
 * Incorpora desglossament d'IVA, retenció IRPF opcional, instantània immutable
 * de l'emissor i del client, i encadenament criptogràfic VeriFactu (Llei Antifrau).
 */
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
#[ORM\Index(columns: ['invoice_number'], name: 'idx_invoice_number')]
#[ORM\Index(columns: ['year', 'series', 'sequential_number'], name: 'idx_invoice_series_seq')]
#[ORM\Index(columns: ['issued_at'], name: 'idx_invoice_issued_at')]
#[ORM\Index(columns: ['status'], name: 'idx_invoice_status')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private string $invoiceNumber;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $series;

    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(type: Types::INTEGER)]
    private int $sequentialNumber;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: InvoiceType::class)]
    private InvoiceType $invoiceType;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: InvoiceStatus::class, options: ['default' => 'paid'])]
    private InvoiceStatus $status = InvoiceStatus::PAID;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: PaymentTransaction::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PaymentTransaction $paymentTransaction = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $originalInvoice = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $issuedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $subtotalCents;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '21.00'])]
    private string $vatRate = '21.00';

    #[ORM\Column(type: Types::INTEGER)]
    private int $vatAmountCents;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '0.00'])]
    private string $irpfRate = '0.00';

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $irpfAmountCents = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalCents;

    #[ORM\Column(type: Types::STRING, length: 3, options: ['default' => 'EUR'])]
    private string $currency = 'EUR';

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $issuerSnapshot = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $customerSnapshot = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $lineItems = [];

    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $previousInvoiceHash = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $currentHash;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $stripeFeeCents = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $issuerSnapshot
     * @param array<string, mixed> $customerSnapshot
     * @param array<int, array<string, mixed>> $lineItems
     */
    public function __construct(
        string $invoiceNumber,
        string $series,
        int $year,
        int $sequentialNumber,
        InvoiceType $invoiceType,
        int $subtotalCents,
        int $vatAmountCents,
        int $totalCents,
        array $issuerSnapshot,
        array $customerSnapshot,
        array $lineItems,
        ?string $previousInvoiceHash = null,
        string $vatRate = '21.00',
        string $irpfRate = '0.00',
        int $irpfAmountCents = 0,
        string $currency = 'EUR'
    ) {
        $this->uuid = Uuid::v7();
        $this->invoiceNumber = $invoiceNumber;
        $this->series = $series;
        $this->year = $year;
        $this->sequentialNumber = $sequentialNumber;
        $this->invoiceType = $invoiceType;
        $this->subtotalCents = $subtotalCents;
        $this->vatRate = $vatRate;
        $this->vatAmountCents = $vatAmountCents;
        $this->irpfRate = $irpfRate;
        $this->irpfAmountCents = $irpfAmountCents;
        $this->totalCents = $totalCents;
        $this->currency = strtoupper($currency);
        $this->issuerSnapshot = $issuerSnapshot;
        $this->customerSnapshot = $customerSnapshot;
        $this->lineItems = $lineItems;
        $this->previousInvoiceHash = $previousInvoiceHash;
        $this->issuedAt = new \DateTimeImmutable();
        $this->paidAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $this->currentHash = $this->calculateVeriFactuHash($previousInvoiceHash);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function getSeries(): string
    {
        return $this->series;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getSequentialNumber(): int
    {
        return $this->sequentialNumber;
    }

    public function getInvoiceType(): InvoiceType
    {
        return $this->invoiceType;
    }

    public function setInvoiceType(InvoiceType $invoiceType): static
    {
        $this->invoiceType = $invoiceType;
        return $this;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPaymentTransaction(): ?PaymentTransaction
    {
        return $this->paymentTransaction;
    }

    public function setPaymentTransaction(?PaymentTransaction $paymentTransaction): static
    {
        $this->paymentTransaction = $paymentTransaction;
        return $this;
    }

    public function getOriginalInvoice(): ?self
    {
        return $this->originalInvoice;
    }

    public function setOriginalInvoice(?self $originalInvoice): static
    {
        $this->originalInvoice = $originalInvoice;
        return $this;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function isWithinWithdrawalPeriod(int $days = 14): bool
    {
        $limit = $this->issuedAt->modify(sprintf('+%d days', $days));
        return new \DateTimeImmutable('now') <= $limit;
    }

    public function getDaysSinceIssued(): int
    {
        $now = new \DateTimeImmutable('now');
        $diff = $now->diff($this->issuedAt);
        return (int) $diff->days;
    }

    public function setIssuedAt(\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getSubtotalCents(): int
    {
        return $this->subtotalCents;
    }

    public function setSubtotalCents(int $subtotalCents): static
    {
        $this->subtotalCents = $subtotalCents;
        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = $vatRate;
        return $this;
    }

    public function getVatAmountCents(): int
    {
        return $this->vatAmountCents;
    }

    public function setVatAmountCents(int $vatAmountCents): static
    {
        $this->vatAmountCents = $vatAmountCents;
        return $this;
    }

    public function getIrpfRate(): string
    {
        return $this->irpfRate;
    }

    public function setIrpfRate(string $irpfRate): static
    {
        $this->irpfRate = $irpfRate;
        return $this;
    }

    public function getIrpfAmountCents(): int
    {
        return $this->irpfAmountCents;
    }

    public function setIrpfAmountCents(int $irpfAmountCents): static
    {
        $this->irpfAmountCents = $irpfAmountCents;
        return $this;
    }

    public function getTotalCents(): int
    {
        return $this->totalCents;
    }

    public function setTotalCents(int $totalCents): static
    {
        $this->totalCents = $totalCents;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getIssuerSnapshot(): array
    {
        return $this->issuerSnapshot;
    }

    /**
     * @param array<string, mixed> $issuerSnapshot
     */
    public function setIssuerSnapshot(array $issuerSnapshot): static
    {
        $this->issuerSnapshot = $issuerSnapshot;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomerSnapshot(): array
    {
        return $this->customerSnapshot;
    }

    /**
     * @param array<string, mixed> $customerSnapshot
     */
    public function setCustomerSnapshot(array $customerSnapshot): static
    {
        $this->customerSnapshot = $customerSnapshot;
        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    /**
     * @param array<int, array<string, mixed>> $lineItems
     */
    public function setLineItems(array $lineItems): static
    {
        $this->lineItems = $lineItems;
        return $this;
    }

    public function getPreviousInvoiceHash(): ?string
    {
        return $this->previousInvoiceHash;
    }

    public function setPreviousInvoiceHash(?string $previousInvoiceHash): static
    {
        $this->previousInvoiceHash = $previousInvoiceHash;
        return $this;
    }

    public function getCurrentHash(): string
    {
        return $this->currentHash;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
    }

    public function getStripeFeeCents(): ?int
    {
        return $this->stripeFeeCents;
    }

    public function setStripeFeeCents(?int $stripeFeeCents): static
    {
        $this->stripeFeeCents = $stripeFeeCents;
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

    public function getTotalFormatted(): string
    {
        return number_format($this->totalCents / 100, 2, ',', '.') . ' €';
    }

    public function getSubtotalFormatted(): string
    {
        return number_format($this->subtotalCents / 100, 2, ',', '.') . ' €';
    }

    public function getVatAmountFormatted(): string
    {
        return number_format($this->vatAmountCents / 100, 2, ',', '.') . ' €';
    }

    public function getIrpfAmountFormatted(): string
    {
        return number_format($this->irpfAmountCents / 100, 2, ',', '.') . ' €';
    }

    /**
     * Calcula el hash criptogràfic SHA-256 encadenat segons els requeriments VeriFactu.
     */
    public function calculateVeriFactuHash(?string $previousHash): string
    {
        $issuerTaxId = (string) ($this->issuerSnapshot['tax_id'] ?? 'B00000000');
        $raw = sprintf(
            '%s|%s|%s|%d|%s',
            $issuerTaxId,
            $this->invoiceNumber,
            $this->issuedAt->format('Y-m-d'),
            $this->totalCents,
            $previousHash ?? 'GENESIS'
        );

        return hash('sha256', $raw);
    }
}

