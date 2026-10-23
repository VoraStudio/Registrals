<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'feature_interest_events')]
#[ORM\Index(columns: ['feature_code'], name: 'idx_feature_interest_code')]
#[ORM\Index(columns: ['created_at'], name: 'idx_feature_interest_created')]
class FeatureInterestEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $featureCode;

    #[ORM\Column(type: 'string', length: 50)]
    private string $origin;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $featureCode, string $origin, ?string $ipAddress = null, ?string $userAgent = null)
    {
        $this->featureCode = $featureCode;
        $this->origin = $origin;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent ? substr($userAgent, 0, 255) : null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeatureCode(): string
    {
        return $this->featureCode;
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
