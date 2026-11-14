<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BlogTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogTranslationRepository::class)]
#[ORM\Table(name: 'blog_translations')]
#[ORM\Index(name: 'idx_blog_translation_lang_hash', columns: ['target_lang', 'hash'])]
class BlogTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 64, unique: true)]
    private string $hash;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $targetLang;

    #[ORM\Column(type: Types::TEXT)]
    private string $sourceText;

    #[ORM\Column(type: Types::TEXT)]
    private string $translatedText;

    #[ORM\Column(type: Types::STRING, length: 120)]
    private string $modelUsed;

    #[ORM\Column(type: Types::INTEGER)]
    private int $hits = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $hash,
        string $targetLang,
        string $sourceText,
        string $translatedText,
        string $modelUsed
    ) {
        $this->hash = $hash;
        $this->targetLang = strtolower(trim($targetLang));
        $this->sourceText = $sourceText;
        $this->translatedText = $translatedText;
        $this->modelUsed = $modelUsed;
        $this->hits = 1;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getTargetLang(): string
    {
        return $this->targetLang;
    }

    public function getSourceText(): string
    {
        return $this->sourceText;
    }

    public function getTranslatedText(): string
    {
        return $this->translatedText;
    }

    public function setTranslatedText(string $translatedText, string $modelUsed): static
    {
        $this->translatedText = $translatedText;
        $this->modelUsed = $modelUsed;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getModelUsed(): string
    {
        return $this->modelUsed;
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function incrementHits(): static
    {
        $this->hits++;
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
}

