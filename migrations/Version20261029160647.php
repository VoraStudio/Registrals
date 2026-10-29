<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029160647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create certificates table for draft and official property certificates';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE certificates (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, certificate_number VARCHAR(50) NOT NULL, mode VARCHAR(20) NOT NULL, status VARCHAR(20) DEFAULT \'valid\' NOT NULL, pdf_file_path VARCHAR(255) NOT NULL, pdf_sha256 VARCHAR(64) NOT NULL, item_fingerprint_at_issue VARCHAR(64) NOT NULL, eidas_signaturit_id VARCHAR(100) DEFAULT NULL, eidas_timestamp DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, item_id INT NOT NULL, issued_by_user_id INT NOT NULL, UNIQUE INDEX UNIQ_8D26FB5FD17F50A6 (uuid), UNIQUE INDEX UNIQ_8D26FB5F3005EFE3 (certificate_number), INDEX idx_cert_uuid (uuid), INDEX idx_cert_number (certificate_number), INDEX idx_cert_item_status (item_id, status), INDEX idx_cert_pdf_sha256 (pdf_sha256), INDEX IDX_8D26FB5F126F525E (item_id), INDEX IDX_8D26FB5FB82713E2 (issued_by_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE certificates ADD CONSTRAINT FK_8D26FB5F126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE certificates ADD CONSTRAINT FK_8D26FB5FB82713E2 FOREIGN KEY (issued_by_user_id) REFERENCES users (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE certificates DROP FOREIGN KEY FK_8D26FB5F126F525E');
        $this->addSql('ALTER TABLE certificates DROP FOREIGN KEY FK_8D26FB5FB82713E2');
        $this->addSql('DROP TABLE certificates');
    }
}
