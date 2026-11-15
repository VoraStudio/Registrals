<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115072108 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE item_history_logs (id INT AUTO_INCREMENT NOT NULL, version INT NOT NULL, event_type VARCHAR(40) NOT NULL, summary VARCHAR(255) NOT NULL, master_sha256 VARCHAR(64) NOT NULL, metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, item_id INT NOT NULL, actor_id INT DEFAULT NULL, INDEX idx_item_history_item_version (item_id, version), INDEX idx_item_history_item_created (item_id, created_at), INDEX IDX_34E04BE1126F525E (item_id), INDEX IDX_34E04BE110DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE item_history_logs ADD CONSTRAINT FK_34E04BE1126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_history_logs ADD CONSTRAINT FK_34E04BE110DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_history_logs DROP FOREIGN KEY FK_34E04BE1126F525E');
        $this->addSql('ALTER TABLE item_history_logs DROP FOREIGN KEY FK_34E04BE110DAF24A');
        $this->addSql('DROP TABLE item_history_logs');
    }
}
