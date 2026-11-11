<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Digital Heritage (Herència Digital) data model: legacy_contacts, succession_claims, nullable Certificate::$item + scope, and Plan thresholds';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE legacy_contacts (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, full_name VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, relationship VARCHAR(80) DEFAULT NULL, status VARCHAR(30) DEFAULT \'pending\' NOT NULL, invitation_token_hash VARCHAR(64) DEFAULT NULL, invitation_expires_at DATETIME DEFAULT NULL, owner_consent_at DATETIME NOT NULL, owner_consent_ip VARCHAR(45) DEFAULT NULL, contact_consented_at DATETIME DEFAULT NULL, contact_consent_ip VARCHAR(45) DEFAULT NULL, privacy_notice_version VARCHAR(20) DEFAULT \'v1.0\' NOT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, beneficiary_user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_20F2E813D17F50A6 (uuid), UNIQUE INDEX UNIQ_20F2E813298D7A97 (invitation_token_hash), INDEX idx_legacy_owner_status (owner_id, status), INDEX idx_legacy_email (email), INDEX IDX_20F2E8137E3C61F9 (owner_id), INDEX IDX_20F2E8137AB7863F (beneficiary_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE succession_claims (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, claim_reference VARCHAR(32) NOT NULL, claimant_name VARCHAR(180) NOT NULL, claimant_email VARCHAR(180) NOT NULL, claimant_tax_id VARCHAR(30) NOT NULL, claimant_email_otp_verified_at DATETIME NOT NULL, opening_fee_paid_cents INT DEFAULT 0 NOT NULL, stripe_payment_intent_id VARCHAR(100) DEFAULT NULL, status VARCHAR(30) DEFAULT \'pending_payment\' NOT NULL, owner_alert_sent_at DATETIME DEFAULT NULL, security_hold_until DATETIME DEFAULT NULL, death_certificate_storage_key VARCHAR(255) NOT NULL, death_certificate_sha256 VARCHAR(64) NOT NULL, succession_title_storage_key VARCHAR(255) NOT NULL, succession_title_sha256 VARCHAR(64) NOT NULL, ai_screening_status VARCHAR(20) DEFAULT \'pending\' NOT NULL, ai_extraction_json JSON DEFAULT NULL, ai_confidence_score DOUBLE PRECISION DEFAULT NULL, reviewed_by_user_uuid VARCHAR(36) DEFAULT NULL, reviewed_by_email VARCHAR(180) DEFAULT NULL, reviewed_at DATETIME DEFAULT NULL, resolution_notes LONGTEXT DEFAULT NULL, executed_transfer_count INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, source_certificate_id INT NOT NULL, deceased_owner_id INT NOT NULL, legacy_contact_id INT DEFAULT NULL, claimant_user_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_6E7C7993D17F50A6 (uuid), UNIQUE INDEX UNIQ_6E7C7993DFD33C7C (claim_reference), INDEX idx_claim_status (status), INDEX idx_claim_owner_status (deceased_owner_id, status), INDEX idx_claim_reference (claim_reference), INDEX IDX_6E7C79933A5B5358 (source_certificate_id), INDEX IDX_6E7C79931EECE1AB (deceased_owner_id), INDEX IDX_6E7C79934150D3FE (legacy_contact_id), INDEX IDX_6E7C7993908F4F4B (claimant_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE legacy_contacts ADD CONSTRAINT FK_20F2E8137E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE legacy_contacts ADD CONSTRAINT FK_20F2E8137AB7863F FOREIGN KEY (beneficiary_user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE succession_claims ADD CONSTRAINT FK_6E7C79933A5B5358 FOREIGN KEY (source_certificate_id) REFERENCES certificates (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE succession_claims ADD CONSTRAINT FK_6E7C79931EECE1AB FOREIGN KEY (deceased_owner_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE succession_claims ADD CONSTRAINT FK_6E7C79934150D3FE FOREIGN KEY (legacy_contact_id) REFERENCES legacy_contacts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE succession_claims ADD CONSTRAINT FK_6E7C7993908F4F4B FOREIGN KEY (claimant_user_id) REFERENCES users (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE certificates ADD scope VARCHAR(30) DEFAULT \'item\' NOT NULL, ADD included_item_cuis_snapshot JSON DEFAULT NULL, ADD claim_token_hash VARCHAR(64) DEFAULT NULL, ADD legacy_contact_id INT DEFAULT NULL, CHANGE item_id item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE certificates ADD CONSTRAINT FK_8D26FB5F4150D3FE FOREIGN KEY (legacy_contact_id) REFERENCES legacy_contacts (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D26FB5F6EC05B66 ON certificates (claim_token_hash)');
        $this->addSql('CREATE INDEX idx_cert_issued_scope ON certificates (issued_by_user_id, scope, status)');
        $this->addSql('CREATE INDEX IDX_8D26FB5F4150D3FE ON certificates (legacy_contact_id)');

        $this->addSql('ALTER TABLE plans ADD allow_legacy_designation TINYINT DEFAULT 0 NOT NULL, ADD max_legacy_contacts INT DEFAULT 0 NOT NULL, ADD legacy_claim_opening_fee_cents INT DEFAULT 1900 NOT NULL');
        $this->addSql("UPDATE plans SET allow_legacy_designation = 0, max_legacy_contacts = 0, legacy_claim_opening_fee_cents = 1900 WHERE code = 'free'");
        $this->addSql("UPDATE plans SET allow_legacy_designation = 1, max_legacy_contacts = 1, legacy_claim_opening_fee_cents = 1900 WHERE code = 'plus'");
        $this->addSql("UPDATE plans SET allow_legacy_designation = 1, max_legacy_contacts = 5, legacy_claim_opening_fee_cents = 1900 WHERE code = 'unlimited'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE succession_claims DROP FOREIGN KEY FK_6E7C79933A5B5358');
        $this->addSql('ALTER TABLE succession_claims DROP FOREIGN KEY FK_6E7C79931EECE1AB');
        $this->addSql('ALTER TABLE succession_claims DROP FOREIGN KEY FK_6E7C79934150D3FE');
        $this->addSql('ALTER TABLE succession_claims DROP FOREIGN KEY FK_6E7C7993908F4F4B');
        $this->addSql('DROP TABLE succession_claims');

        $this->addSql('ALTER TABLE certificates DROP FOREIGN KEY FK_8D26FB5F4150D3FE');
        $this->addSql('DROP INDEX UNIQ_8D26FB5F6EC05B66 ON certificates');
        $this->addSql('DROP INDEX idx_cert_issued_scope ON certificates');
        $this->addSql('DROP INDEX IDX_8D26FB5F4150D3FE ON certificates');
        $this->addSql('ALTER TABLE certificates DROP scope, DROP included_item_cuis_snapshot, DROP claim_token_hash, DROP legacy_contact_id, CHANGE item_id item_id INT NOT NULL');

        $this->addSql('ALTER TABLE legacy_contacts DROP FOREIGN KEY FK_20F2E8137E3C61F9');
        $this->addSql('ALTER TABLE legacy_contacts DROP FOREIGN KEY FK_20F2E8137AB7863F');
        $this->addSql('DROP TABLE legacy_contacts');

        $this->addSql('ALTER TABLE plans DROP allow_legacy_designation, DROP max_legacy_contacts, DROP legacy_claim_opening_fee_cents');
    }
}

