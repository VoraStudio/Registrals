<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261114115925 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blog_translations (id INT AUTO_INCREMENT NOT NULL, hash VARCHAR(64) NOT NULL, target_lang VARCHAR(10) NOT NULL, source_text LONGTEXT NOT NULL, translated_text LONGTEXT NOT NULL, model_used VARCHAR(120) NOT NULL, hits INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_9C6B3500D1B862B8 (hash), INDEX idx_blog_translation_lang_hash (target_lang, hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE blog_translations');
    }
}
