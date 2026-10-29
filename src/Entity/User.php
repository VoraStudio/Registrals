<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\KycStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(columns: ['email'], name: 'idx_users_email')]
#[ORM\Index(columns: ['auth_provider', 'auth_provider_id'], name: 'idx_users_oauth')]
#[UniqueEntity(fields: ['email'], message: 'Ja existeix un compte registrat amb aquesta adreça electrònica.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $uuid;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 150)]
    private string $lastName;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $taxId = null;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 2, options: ['default' => 'ES'])]
    private string $countryCode = 'ES';

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $blockedAt = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $blockReason = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'local'])]
    private string $authProvider = 'local';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $authProviderId = null;

    #[ORM\Column(type: 'string', length: 30, enumType: KycStatus::class, options: ['default' => 'unverified'])]
    private KycStatus $kycStatus = KycStatus::UNVERIFIED;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $signaturitKycId = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $identityVerifiedAt = null;

    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: true)]
    private ?Plan $plan = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $planStartedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $planExpiresAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $aiUsageCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $certificatesIssuedCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lifetimeItemsRegistered = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $onboardingCompleted = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var \Doctrine\Common\Collections\Collection<int, Item>
     */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Item::class)]
    private \Doctrine\Common\Collections\Collection $items;

    public function __construct()
    {
        $this->uuid = Uuid::v7();
        $this->items = new \Doctrine\Common\Collections\ArrayCollection();
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): static
    {
        $this->taxId = $taxId;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): static
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getAuthProvider(): string
    {
        return $this->authProvider;
    }

    public function setAuthProvider(string $authProvider): static
    {
        $this->authProvider = $authProvider;
        return $this;
    }

    public function getAuthProviderId(): ?string
    {
        return $this->authProviderId;
    }

    public function setAuthProviderId(?string $authProviderId): static
    {
        $this->authProviderId = $authProviderId;
        return $this;
    }

    public function getKycStatus(): KycStatus
    {
        return $this->kycStatus;
    }

    public function setKycStatus(KycStatus $kycStatus): static
    {
        $this->kycStatus = $kycStatus;
        return $this;
    }

    public function getSignaturitKycId(): ?string
    {
        return $this->signaturitKycId;
    }

    public function setSignaturitKycId(?string $signaturitKycId): static
    {
        $this->signaturitKycId = $signaturitKycId;
        return $this;
    }

    public function getIdentityVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->identityVerifiedAt;
    }

    public function setIdentityVerifiedAt(?\DateTimeImmutable $identityVerifiedAt): static
    {
        $this->identityVerifiedAt = $identityVerifiedAt;
        return $this;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function setPlan(?Plan $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getPlanStartedAt(): ?\DateTimeImmutable
    {
        return $this->planStartedAt;
    }

    public function setPlanStartedAt(?\DateTimeImmutable $planStartedAt): static
    {
        $this->planStartedAt = $planStartedAt;
        return $this;
    }

    public function getPlanExpiresAt(): ?\DateTimeImmutable
    {
        return $this->planExpiresAt;
    }

    public function setPlanExpiresAt(?\DateTimeImmutable $planExpiresAt): static
    {
        $this->planExpiresAt = $planExpiresAt;
        return $this;
    }

    public function getAiUsageCount(): int
    {
        return $this->aiUsageCount;
    }

    public function setAiUsageCount(int $aiUsageCount): static
    {
        $this->aiUsageCount = $aiUsageCount;
        return $this;
    }

    public function incrementAiUsage(): static
    {
        $this->aiUsageCount++;
        return $this;
    }

    public function getCertificatesIssuedCount(): int
    {
        return $this->certificatesIssuedCount;
    }

    public function setCertificatesIssuedCount(int $certificatesIssuedCount): static
    {
        $this->certificatesIssuedCount = $certificatesIssuedCount;
        return $this;
    }

    public function getLifetimeItemsRegistered(): int
    {
        return $this->lifetimeItemsRegistered;
    }

    public function setLifetimeItemsRegistered(int $lifetimeItemsRegistered): static
    {
        $this->lifetimeItemsRegistered = $lifetimeItemsRegistered;
        return $this;
    }

    public function isOnboardingCompleted(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->onboardingCompleted;
    }

    public function setOnboardingCompleted(bool $onboardingCompleted): static
    {
        $this->onboardingCompleted = $onboardingCompleted;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getBlockedAt(): ?\DateTimeImmutable
    {
        return $this->blockedAt;
    }

    public function setBlockedAt(?\DateTimeImmutable $blockedAt): static
    {
        $this->blockedAt = $blockedAt;
        return $this;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    public function setBlockReason(?string $blockReason): static
    {
        $this->blockReason = $blockReason;
        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true);
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, Item>
     */
    public function getItems(): \Doctrine\Common\Collections\Collection
    {
        return $this->items;
    }
}
