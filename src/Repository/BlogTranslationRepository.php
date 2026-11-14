<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BlogTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BlogTranslation>
 */
class BlogTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BlogTranslation::class);
    }

    public static function generateHash(string $text, string $targetLang): string
    {
        return hash('sha256', strtolower(trim($targetLang)) . ':' . trim($text));
    }

    public function findByHash(string $hash): ?BlogTranslation
    {
        return $this->findOneBy(['hash' => $hash]);
    }

    public function saveTranslation(
        string $hash,
        string $targetLang,
        string $sourceText,
        string $translatedText,
        string $modelUsed
    ): BlogTranslation {
        $existing = $this->findByHash($hash);
        if ($existing !== null) {
            $existing->setTranslatedText($translatedText, $modelUsed);
            $this->getEntityManager()->flush();
            return $existing;
        }

        $entity = new BlogTranslation($hash, $targetLang, $sourceText, $translatedText, $modelUsed);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();

        return $entity;
    }
}

