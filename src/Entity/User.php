<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailOtpPurpose;
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
#[ORM\Index(columns: ['reset_password_token_hash'], name: 'idx_users_reset_token')]
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

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $kycReferenceId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $kycLastReason = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $kycPaidCredits = 0;

    #[ORM\ManyToOne(targetEntity: Plan::class)]
    #[ORM\JoinColumn(name: 'plan_id', referencedColumnName: 'id', nullable: true)]
    private ?Plan $plan = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $planStartedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $planExpiresAt = null;

    #[ORM\Column(type: 'string', length: 100, unique: true, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $subscriptionStatus = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $aiUsageCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $certificatesIssuedCount = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $lifetimeItemsRegistered = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $periodItemsRegistered = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $periodStartedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $onboardingCompleted = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $legalTermsAcceptedAt = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $legalTermsVersion = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $registrationIp = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $downgradeGracePeriodExpiresAt = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $resetPasswordTokenHash = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetPasswordExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $twoFactorEnabled = false;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $emailOtpHash = null;

    #[ORM\Column(type: 'string', length: 30, enumType: EmailOtpPurpose::class, nullable: true)]
    private ?EmailOtpPurpose $emailOtpPurpose = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailOtpExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailOtpSentAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $emailOtpAttempts = 0;

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
        $first = isset($this->firstName) ? $this->firstName : '';
        $last = isset($this->lastName) ? $this->lastName : '';

        return trim($first . ' ' . $last);
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

    public function getKycReferenceId(): ?string
    {
        return $this->kycReferenceId;
    }

    public function setKycReferenceId(?string $kycReferenceId): static
    {
        $this->kycReferenceId = $kycReferenceId;
        return $this;
    }

    public function getKycLastReason(): ?string
    {
        return $this->kycLastReason;
    }

    public function setKycLastReason(?string $kycLastReason): static
    {
        $this->kycLastReason = $kycLastReason;
        return $this;
    }

    public function getKycPaidCredits(): int
    {
        return $this->kycPaidCredits;
    }

    public function setKycPaidCredits(int $kycPaidCredits): static
    {
        $this->kycPaidCredits = max(0, $kycPaidCredits);
        return $this;
    }

    public function incrementKycPaidCredits(int $delta = 1): static
    {
        $this->kycPaidCredits += max(0, $delta);
        return $this;
    }

    public function consumeKycPaidCredit(): bool
    {
        if ($this->kycPaidCredits <= 0) {
            return false;
        }

        --$this->kycPaidCredits;
        return true;
    }

    public function isIdentityVerified(): bool
    {
        return $this->kycStatus === KycStatus::APPROVED;
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

    public function getPeriodItemsRegistered(): int
    {
        return $this->periodItemsRegistered;
    }

    public function setPeriodItemsRegistered(int $periodItemsRegistered): static
    {
        $this->periodItemsRegistered = $periodItemsRegistered;
        return $this;
    }

    public function incrementPeriodItemsRegistered(): static
    {
        $this->periodItemsRegistered++;
        return $this;
    }

    public function getPeriodStartedAt(): ?\DateTimeImmutable
    {
        return $this->periodStartedAt;
    }

    public function setPeriodStartedAt(?\DateTimeImmutable $periodStartedAt): static
    {
        $this->periodStartedAt = $periodStartedAt;
        return $this;
    }

    public function getCurrentPeriodStart(): \DateTimeImmutable
    {
        if ($this->periodStartedAt !== null) {
            return $this->periodStartedAt;
        }

        return $this->planStartedAt ?? $this->createdAt;
    }

    public function isRegistrationPeriodExpired(): bool
    {
        $start = $this->getCurrentPeriodStart();
        $now = new \DateTimeImmutable();

        return $now >= $start->modify('+1 year');
    }

    public function resetRegistrationPeriod(): static
    {
        $this->periodStartedAt = new \DateTimeImmutable();
        $this->periodItemsRegistered = 0;

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

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;
        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): static
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        return $this;
    }

    public function getSubscriptionStatus(): ?string
    {
        return $this->subscriptionStatus;
    }

    public function setSubscriptionStatus(?string $subscriptionStatus): static
    {
        $this->subscriptionStatus = $subscriptionStatus;
        return $this;
    }

    public function getLegalTermsAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->legalTermsAcceptedAt;
    }

    public function setLegalTermsAcceptedAt(?\DateTimeImmutable $legalTermsAcceptedAt): static
    {
        $this->legalTermsAcceptedAt = $legalTermsAcceptedAt;
        return $this;
    }

    public function getLegalTermsVersion(): ?string
    {
        return $this->legalTermsVersion;
    }

    public function setLegalTermsVersion(?string $legalTermsVersion): static
    {
        $this->legalTermsVersion = $legalTermsVersion;
        return $this;
    }

    public function getRegistrationIp(): ?string
    {
        return $this->registrationIp;
    }

    public function setRegistrationIp(?string $registrationIp): static
    {
        $this->registrationIp = $registrationIp;
        return $this;
    }

    public function hasAcceptedLegalTerms(string $currentVersion = 'v1.0'): bool
    {
        return $this->legalTermsVersion === $currentVersion && $this->legalTermsAcceptedAt !== null;
    }

    public function getDowngradeGracePeriodExpiresAt(): ?\DateTimeImmutable
    {
        return $this->downgradeGracePeriodExpiresAt;
    }

    public function setDowngradeGracePeriodExpiresAt(?\DateTimeImmutable $downgradeGracePeriodExpiresAt): static
    {
        $this->downgradeGracePeriodExpiresAt = $downgradeGracePeriodExpiresAt;
        return $this;
    }

    public function isInDowngradeGracePeriod(): bool
    {
        return $this->downgradeGracePeriodExpiresAt !== null && $this->downgradeGracePeriodExpiresAt > new \DateTimeImmutable();
    }

    public function getDowngradeGraceDaysLeft(): int
    {
        if (!$this->isInDowngradeGracePeriod() || $this->downgradeGracePeriodExpiresAt === null) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->downgradeGracePeriodExpiresAt);
        return max(1, (int) $diff->days);
    }

    public function getResetPasswordTokenHash(): ?string
    {
        return $this->resetPasswordTokenHash;
    }

    public function setResetPasswordTokenHash(?string $resetPasswordTokenHash): static
    {
        $this->resetPasswordTokenHash = $resetPasswordTokenHash;
        return $this;
    }

    public function getResetPasswordExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetPasswordExpiresAt;
    }

    public function setResetPasswordExpiresAt(?\DateTimeImmutable $resetPasswordExpiresAt): static
    {
        $this->resetPasswordExpiresAt = $resetPasswordExpiresAt;
        return $this;
    }

    public function isResetPasswordTokenValid(): bool
    {
        return $this->resetPasswordTokenHash !== null
            && $this->resetPasswordExpiresAt !== null
            && $this->resetPasswordExpiresAt > new \DateTimeImmutable();
    }

    public function clearResetPasswordToken(): static
    {
        $this->resetPasswordTokenHash = null;
        $this->resetPasswordExpiresAt = null;
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function markEmailVerified(?\DateTimeImmutable $verifiedAt = null): static
    {
        if ($this->emailVerifiedAt === null) {
            $this->emailVerifiedAt = $verifiedAt ?? new \DateTimeImmutable();
        }

        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function setTwoFactorEnabled(bool $twoFactorEnabled): static
    {
        $this->twoFactorEnabled = $twoFactorEnabled;
        return $this;
    }

    public function requiresLoginTwoFactor(): bool
    {
        return $this->authProvider === 'local' && $this->twoFactorEnabled;
    }

    public function getEmailOtpHash(): ?string
    {
        return $this->emailOtpHash;
    }

    public function setEmailOtpHash(?string $emailOtpHash): static
    {
        $this->emailOtpHash = $emailOtpHash;
        return $this;
    }

    public function getEmailOtpPurpose(): ?EmailOtpPurpose
    {
        return $this->emailOtpPurpose;
    }

    public function setEmailOtpPurpose(?EmailOtpPurpose $emailOtpPurpose): static
    {
        $this->emailOtpPurpose = $emailOtpPurpose;
        return $this;
    }

    public function getEmailOtpExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailOtpExpiresAt;
    }

    public function setEmailOtpExpiresAt(?\DateTimeImmutable $emailOtpExpiresAt): static
    {
        $this->emailOtpExpiresAt = $emailOtpExpiresAt;
        return $this;
    }

    public function getEmailOtpSentAt(): ?\DateTimeImmutable
    {
        return $this->emailOtpSentAt;
    }

    public function setEmailOtpSentAt(?\DateTimeImmutable $emailOtpSentAt): static
    {
        $this->emailOtpSentAt = $emailOtpSentAt;
        return $this;
    }

    public function getEmailOtpAttempts(): int
    {
        return $this->emailOtpAttempts;
    }

    public function setEmailOtpAttempts(int $emailOtpAttempts): static
    {
        $this->emailOtpAttempts = max(0, $emailOtpAttempts);
        return $this;
    }

    public function hasActiveEmailOtp(EmailOtpPurpose $purpose): bool
    {
        return $this->emailOtpHash !== null
            && $this->emailOtpPurpose === $purpose
            && $this->emailOtpExpiresAt !== null
            && $this->emailOtpExpiresAt > new \DateTimeImmutable();
    }

    public function clearEmailOtp(): static
    {
        $this->emailOtpHash = null;
        $this->emailOtpPurpose = null;
        $this->emailOtpExpiresAt = null;
        $this->emailOtpSentAt = null;
        $this->emailOtpAttempts = 0;

        return $this;
    }
}
