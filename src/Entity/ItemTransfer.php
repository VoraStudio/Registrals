<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TransferStatus;
use App\Repository\ItemTransferRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ItemTransferRepository::class)]
#[ORM\Table(name: 'item_transfers')]
#[ORM\Index(columns: ['token'], name: 'idx_transfer_token')]
#[ORM\Index(columns: ['item_id', 'status'], name: 'idx_transfer_item_status')]
#[ORM\Index(columns: ['recipient_email', 'status'], name: 'idx_transfer_recipient_status')]
#[ORM\Index(columns: ['sender_id', 'status'], name: 'idx_transfer_sender_status')]
class ItemTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: Item::class, inversedBy: 'transfers')]
    #[ORM\JoinColumn(name: 'item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private User $sender;

    #[ORM\Column(type: 'string', length: 180)]
    private string $recipientEmail;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $recipient = null;

    /**
     * Token criptogràfic únic de 64 caràcters per a l'enllaç segur d'acceptació (caduca a les 72h)
     */
    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $token;

    /**
     * Codi PIN de seguretat opcional de 6 dígits (per a doble factor notarial entre parts)
     */
    #[ORM\Column(type: 'string', length: 6, nullable: true)]
    private ?string $securityCode = null;

    #[ORM\Column(type: 'string', length: 25, enumType: TransferStatus::class, options: ['default' => 'pending'])]
    private TransferStatus $status = TransferStatus::PENDING;

    /**
     * Taxa de traspàs en cèntims d'euro (290 = 2,90 € o 0 si el pla del remitent té traspassos inclosos)
     */
    #[ORM\Column(type: 'integer', options: ['default' => 290])]
    private int $transferFeeCents = 290;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $senderIp = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $recipientIp = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rejectionReason = null;

    public function __construct(
        Item $item,
        User $sender,
        string $recipientEmail,
        int $transferFeeCents = 290,
        ?int $durationHours = 72,
        ?string $senderIp = null,
        ?string $securityCode = null
    ) {
        $this->uuid = Uuid::v7();
        $this->item = $item;
        $this->sender = $sender;
        $this->recipientEmail = strtolower(trim($recipientEmail));
        $this->transferFeeCents = $transferFeeCents;
        $this->senderIp = $senderIp;
        $this->securityCode = $securityCode;
        $this->token = bin2hex(random_bytes(32)); // 64 hex characters
        $this->status = TransferStatus::PENDING;
        $this->requestedAt = new DateTimeImmutable();
        $this->expiresAt = $this->requestedAt->modify(sprintf('+%d hours', $durationHours ?? 72));
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

    public function setItem(Item $item): static
    {
        $this->item = $item;
        return $this;
    }

    public function getSender(): User
    {
        return $this->sender;
    }

    public function setSender(User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(string $recipientEmail): static
    {
        $this->recipientEmail = strtolower(trim($recipientEmail));
        return $this;
    }

    public function getRecipient(): ?User
    {
        return $this->recipient;
    }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getSecurityCode(): ?string
    {
        return $this->securityCode;
    }

    public function setSecurityCode(?string $securityCode): static
    {
        $this->securityCode = $securityCode;
        return $this;
    }

    public function getStatus(): TransferStatus
    {
        return $this->status;
    }

    public function setStatus(TransferStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTransferFeeCents(): int
    {
        return $this->transferFeeCents;
    }

    public function setTransferFeeCents(int $transferFeeCents): static
    {
        $this->transferFeeCents = $transferFeeCents;
        return $this;
    }

    public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getSenderIp(): ?string
    {
        return $this->senderIp;
    }

    public function setSenderIp(?string $senderIp): static
    {
        $this->senderIp = $senderIp;
        return $this;
    }

    public function getRecipientIp(): ?string
    {
        return $this->recipientIp;
    }

    public function setRecipientIp(?string $recipientIp): static
    {
        $this->recipientIp = $recipientIp;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function isExpired(): bool
    {
        if ($this->status === TransferStatus::EXPIRED) {
            return true;
        }

        return $this->status === TransferStatus::PENDING && new DateTimeImmutable() > $this->expiresAt;
    }

    public function canBeAcceptedBy(User $user): bool
    {
        if ($this->isExpired() || $this->status !== TransferStatus::PENDING) {
            return false;
        }

        if ($user === $this->sender || strtolower($user->getEmail()) === strtolower($this->sender->getEmail())) {
            return false;
        }

        return strtolower($user->getEmail()) === strtolower($this->recipientEmail);
    }

    public function accept(User $recipient, ?string $recipientIp = null): void
    {
        $this->status = TransferStatus::ACCEPTED;
        $this->recipient = $recipient;
        $this->recipientIp = $recipientIp;
        $this->completedAt = new DateTimeImmutable();
    }

    public function reject(string $reason, ?string $recipientIp = null): void
    {
        $this->status = TransferStatus::REJECTED;
        $this->rejectionReason = $reason;
        $this->recipientIp = $recipientIp;
        $this->completedAt = new DateTimeImmutable();
    }

    public function cancel(?string $senderIp = null): void
    {
        $this->status = TransferStatus::CANCELLED;
        if ($senderIp !== null) {
            $this->senderIp = $senderIp;
        }
        $this->cancelledAt = new DateTimeImmutable();
    }
}
