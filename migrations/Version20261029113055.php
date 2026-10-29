<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029113055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create blockchain_receipts table for Polygon Layer 2 notarization';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blockchain_receipts (id INT AUTO_INCREMENT NOT NULL, certificate_id INT DEFAULT NULL, action_type VARCHAR(30) NOT NULL, network VARCHAR(50) DEFAULT \'polygon-pos\' NOT NULL, contract_address VARCHAR(42) NOT NULL, tx_hash VARCHAR(66) NOT NULL, block_number BIGINT NOT NULL, anchored_hash VARCHAR(64) NOT NULL, anchored_at DATETIME NOT NULL, item_id INT NOT NULL, INDEX idx_receipt_item_action (item_id, action_type), INDEX idx_receipt_tx (tx_hash), INDEX idx_receipt_anchored_hash (anchored_hash), INDEX IDX_2108F7FF126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blockchain_receipts ADD CONSTRAINT FK_2108F7FF126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blockchain_receipts DROP FOREIGN KEY FK_2108F7FF126F525E');
        $this->addSql('DROP TABLE blockchain_receipts');
    }
}
