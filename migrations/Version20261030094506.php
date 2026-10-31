<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261030094506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE item_transfers (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, recipient_email VARCHAR(180) NOT NULL, token VARCHAR(64) NOT NULL, security_code VARCHAR(6) DEFAULT NULL, status VARCHAR(25) DEFAULT \'pending\' NOT NULL, transfer_fee_cents INT DEFAULT 290 NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, sender_ip VARCHAR(45) DEFAULT NULL, recipient_ip VARCHAR(45) DEFAULT NULL, rejection_reason VARCHAR(255) DEFAULT NULL, item_id INT NOT NULL, sender_id INT NOT NULL, recipient_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_8CF02FE8D17F50A6 (uuid), UNIQUE INDEX UNIQ_8CF02FE85F37A13B (token), INDEX idx_transfer_token (token), INDEX idx_transfer_item_status (item_id, status), INDEX idx_transfer_recipient_status (recipient_email, status), INDEX idx_transfer_sender_status (sender_id, status), INDEX IDX_8CF02FE8126F525E (item_id), INDEX IDX_8CF02FE8F624B39D (sender_id), INDEX IDX_8CF02FE8E92F8F78 (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE item_transfers ADD CONSTRAINT FK_8CF02FE8126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_transfers ADD CONSTRAINT FK_8CF02FE8F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE item_transfers ADD CONSTRAINT FK_8CF02FE8E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_transfers DROP FOREIGN KEY FK_8CF02FE8126F525E');
        $this->addSql('ALTER TABLE item_transfers DROP FOREIGN KEY FK_8CF02FE8F624B39D');
        $this->addSql('ALTER TABLE item_transfers DROP FOREIGN KEY FK_8CF02FE8E92F8F78');
        $this->addSql('DROP TABLE item_transfers');
    }
}
