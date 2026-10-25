<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261025144622 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE system_settings (id INT AUTO_INCREMENT NOT NULL, setting_key VARCHAR(64) NOT NULL, setting_value LONGTEXT DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8CAF11475FA1E697 (setting_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE plans ADD ai_assistance_enabled TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE users ADD is_active TINYINT DEFAULT 1 NOT NULL, ADD blocked_at DATETIME DEFAULT NULL, ADD block_reason VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE system_settings');
        $this->addSql('ALTER TABLE plans DROP ai_assistance_enabled');
        $this->addSql('ALTER TABLE users DROP is_active, DROP blocked_at, DROP block_reason');
    }
}
