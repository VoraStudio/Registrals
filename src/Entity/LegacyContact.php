<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\LegacyContactStatus;
use App\Repository\LegacyContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: LegacyContactRepository::class)]
#[ORM\Table(name: 'legacy_contacts')]
#[ORM\Index(columns: ['owner_id', 'status'], name: 'idx_legacy_owner_status')]
#[ORM\Index(columns: ['email'], name: 'idx_legacy_email')]
class LegacyContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(type: 'string', length: 180)]
    private string $fullName;

    #[ORM\Column(type: 'string', length: 180)]
    private string $email;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $relationship = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'beneficiary_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $beneficiaryUser = null;

    #[ORM\Column(type: 'string', length: 30, enumType: LegacyContactStatus::class, options: ['default' => 'pending'])]
    private LegacyContactStatus $status = LegacyContactStatus::PENDING;

    #[ORM\Column(type: 'string', length: 64, unique: true, nullable: true)]
    private ?string $invitationTokenHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invitationExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $ownerConsentAt;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ownerConsentIp = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $contactConsentedAt = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $contactConsentIp = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'v1.0'])]
    private string $privacyNoticeVersion = 'v1.0';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        User $owner,
        string $fullName,
        string $email,
        ?string $relationship = null,
        ?string $ownerConsentIp = null,
        ?\DateTimeImmutable $ownerConsentAt = null,
        string $privacyNoticeVersion = 'v1.0',
        ?Uuid $uuid = null
    ) {
        $now = new \DateTimeImmutable();
        $this->uuid = $uuid ?? Uuid::v7();
        $this->owner = $owner;
        $this->fullName = trim($fullName);
        $this->email = mb_strtolower(trim($email));
        $this->relationship = $relationship !== null ? trim($relationship) : null;
        $this->ownerConsentAt = $ownerConsentAt ?? $now;
        $this->ownerConsentIp = $ownerConsentIp;
        $this->privacyNoticeVersion = $privacyNoticeVersion;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = trim($fullName);
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function setRelationship(?string $relationship): static
    {
        $this->relationship = $relationship !== null ? trim($relationship) : null;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getBeneficiaryUser(): ?User
    {
        return $this->beneficiaryUser;
    }

    public function setBeneficiaryUser(?User $beneficiaryUser): static
    {
        $this->beneficiaryUser = $beneficiaryUser;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isDesignatedUser(User $user): bool
    {
        if ($this->beneficiaryUser?->getId() !== null && $user->getId() !== null) {
            if ($this->beneficiaryUser->getId() === $user->getId()) {
                return true;
            }
        }

        return mb_strtolower(trim($user->getEmail())) === $this->email;
    }

    public function getStatus(): LegacyContactStatus
    {
        return $this->status;
    }

    public function setStatus(LegacyContactStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getInvitationTokenHash(): ?string
    {
        return $this->invitationTokenHash;
    }

    public function setInvitationTokenHash(?string $invitationTokenHash): static
    {
        $this->invitationTokenHash = $invitationTokenHash;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getInvitationExpiresAt(): ?\DateTimeImmutable
    {
        return $this->invitationExpiresAt;
    }

    public function setInvitationExpiresAt(?\DateTimeImmutable $invitationExpiresAt): static
    {
        $this->invitationExpiresAt = $invitationExpiresAt;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function issueInvitationToken(string $tokenHash, \DateTimeImmutable $expiresAt): static
    {
        $this->invitationTokenHash = $tokenHash;
        $this->invitationExpiresAt = $expiresAt;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isInvitationExpired(?\DateTimeImmutable $reference = null): bool
    {
        if ($this->invitationExpiresAt === null) {
            return true;
        }

        return $this->invitationExpiresAt <= ($reference ?? new \DateTimeImmutable());
    }

    public function accept(User $beneficiaryUser, ?string $consentIp = null): static
    {
        $now = new \DateTimeImmutable();
        $this->beneficiaryUser = $beneficiaryUser;
        $this->status = LegacyContactStatus::ACCEPTED;
        $this->contactConsentedAt = $now;
        $this->contactConsentIp = $consentIp;
        $this->invitationTokenHash = null;
        $this->updatedAt = $now;

        return $this;
    }

    public function decline(?string $consentIp = null): static
    {
        $now = new \DateTimeImmutable();
        $this->status = LegacyContactStatus::DECLINED;
        $this->contactConsentedAt = $now;
        $this->contactConsentIp = $consentIp;
        $this->invitationTokenHash = null;
        $this->updatedAt = $now;

        return $this;
    }

    public function revoke(): static
    {
        $now = new \DateTimeImmutable();
        $this->status = LegacyContactStatus::REVOKED;
        $this->revokedAt = $now;
        $this->invitationTokenHash = null;
        $this->updatedAt = $now;

        return $this;
    }

    public function getOwnerConsentAt(): \DateTimeImmutable
    {
        return $this->ownerConsentAt;
    }

    public function getOwnerConsentIp(): ?string
    {
        return $this->ownerConsentIp;
    }

    public function getContactConsentedAt(): ?\DateTimeImmutable
    {
        return $this->contactConsentedAt;
    }

    public function getContactConsentIp(): ?string
    {
        return $this->contactConsentIp;
    }

    public function getPrivacyNoticeVersion(): string
    {
        return $this->privacyNoticeVersion;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

