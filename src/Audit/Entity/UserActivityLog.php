<?php

declare(strict_types=1);

namespace App\Audit\Entity;

use App\Audit\Enum\UserActivityAction;
use App\Audit\Repository\UserActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActivityLogRepository::class)]
#[ORM\Table(name: 'user_activity_logs')]
#[ORM\Index(columns: ['user_uuid'], name: 'idx_activity_user_uuid')]
#[ORM\Index(columns: ['created_at'], name: 'idx_activity_created_at')]
class UserActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $userUuid;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $userEmail;

    #[ORM\Column(type: Types::STRING, length: 180)]
    private string $userName;

    #[ORM\Column(type: Types::STRING, length: 50, enumType: UserActivityAction::class)]
    private UserActivityAction $action;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $description;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $userAgent = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $details = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        string $userUuid,
        string $userEmail,
        string $userName,
        UserActivityAction $action,
        string $description,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?array $details = null
    ) {
        $this->userUuid = $userUuid;
        $this->userEmail = $userEmail;
        $this->userName = $userName;
        $this->action = $action;
        $this->description = $description;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent !== null ? mb_substr($userAgent, 0, 255) : null;
        $this->details = $details;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserUuid(): string
    {
        return $this->userUuid;
    }

    public function getUserEmail(): string
    {
        return $this->userEmail;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getAction(): UserActivityAction
    {
        return $this->action;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
