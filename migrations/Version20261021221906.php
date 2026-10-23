<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021221906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plans (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(50) NOT NULL, name VARCHAR(100) NOT NULL, price_cents INT DEFAULT 0 NOT NULL, billing_period VARCHAR(20) DEFAULT \'yearly\' NOT NULL, max_active_items INT DEFAULT 20 NOT NULL, max_lifetime_items INT DEFAULT 50 NOT NULL, monthly_certificates_quota INT DEFAULT 0 NOT NULL, allow_official_certificates TINYINT DEFAULT 0 NOT NULL, ai_assistance_quota INT DEFAULT 0 NOT NULL, transfers_included INT DEFAULT 0 NOT NULL, extra_transfer_price_cents INT DEFAULT 200 NOT NULL, max_documents_per_item INT DEFAULT 4 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_356798D177153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(150) NOT NULL, tax_id VARCHAR(20) DEFAULT NULL, country_code VARCHAR(2) DEFAULT \'ES\' NOT NULL, auth_provider VARCHAR(20) DEFAULT \'local\' NOT NULL, auth_provider_id VARCHAR(255) DEFAULT NULL, kyc_status VARCHAR(30) DEFAULT \'unverified\' NOT NULL, signaturit_kyc_id VARCHAR(100) DEFAULT NULL, identity_verified_at DATETIME DEFAULT NULL, plan_started_at DATETIME NOT NULL, plan_expires_at DATETIME DEFAULT NULL, ai_usage_count INT DEFAULT 0 NOT NULL, certificates_issued_count INT DEFAULT 0 NOT NULL, lifetime_items_registered INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, plan_id INT NOT NULL, UNIQUE INDEX UNIQ_1483A5E9D17F50A6 (uuid), UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), INDEX idx_users_email (email), INDEX idx_users_oauth (auth_provider, auth_provider_id), INDEX IDX_1483A5E9E899029B (plan_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9E899029B FOREIGN KEY (plan_id) REFERENCES plans (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9E899029B');
        $this->addSql('DROP TABLE plans');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
