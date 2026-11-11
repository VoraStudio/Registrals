<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_visits')]
#[ORM\Index(columns: ['country_code'], name: 'idx_site_visits_country')]
#[ORM\Index(columns: ['created_at'], name: 'idx_site_visits_created')]
class SiteVisit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $path;

    #[ORM\Column(type: 'string', length: 2)]
    private string $countryCode;

    #[ORM\Column(type: 'string', length: 64)]
    private string $ipHash;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'desktop'])]
    private string $deviceType = 'desktop';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $path,
        string $countryCode,
        string $ipHash,
        string $deviceType = 'desktop'
    ) {
        $this->path = mb_substr($path, 0, 255);
        $this->countryCode = strtoupper(substr($countryCode, 0, 2));
        $this->ipHash = $ipHash;
        $this->deviceType = $deviceType;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getIpHash(): string
    {
        return $this->ipHash;
    }

    public function getDeviceType(): string
    {
        return $this->deviceType;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

