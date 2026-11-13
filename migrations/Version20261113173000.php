<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create billing_configs and invoices tables with issuer snapshot and VeriFactu hash chaining';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE billing_configs (
            id INT AUTO_INCREMENT NOT NULL,
            legal_name VARCHAR(255) NOT NULL,
            tax_id VARCHAR(30) NOT NULL,
            address VARCHAR(255) NOT NULL,
            postal_code VARCHAR(20) NOT NULL,
            city VARCHAR(100) NOT NULL,
            province VARCHAR(100) NOT NULL,
            country_code VARCHAR(2) DEFAULT \'ES\' NOT NULL,
            mercantile_registry VARCHAR(255) DEFAULT NULL,
            billing_email VARCHAR(180) NOT NULL,
            default_vat_rate NUMERIC(5, 2) DEFAULT \'21.00\' NOT NULL,
            default_irpf_rate NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            series_complete VARCHAR(20) DEFAULT \'REG-\' NOT NULL,
            series_simplified VARCHAR(20) DEFAULT \'FS-\' NOT NULL,
            series_rectification VARCHAR(20) DEFAULT \'R-\' NOT NULL,
            next_number_complete INT DEFAULT 1 NOT NULL,
            next_number_simplified INT DEFAULT 1 NOT NULL,
            next_number_rectification INT DEFAULT 1 NOT NULL,
            footer_legal_text LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE invoices (
            id INT AUTO_INCREMENT NOT NULL,
            uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\',
            invoice_number VARCHAR(50) NOT NULL,
            series VARCHAR(20) NOT NULL,
            year INT NOT NULL,
            sequential_number INT NOT NULL,
            invoice_type VARCHAR(10) NOT NULL,
            status VARCHAR(20) DEFAULT \'paid\' NOT NULL,
            user_id INT DEFAULT NULL,
            payment_transaction_id INT DEFAULT NULL,
            original_invoice_id INT DEFAULT NULL,
            issued_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            subtotal_cents INT NOT NULL,
            vat_rate NUMERIC(5, 2) DEFAULT \'21.00\' NOT NULL,
            vat_amount_cents INT NOT NULL,
            irpf_rate NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL,
            irpf_amount_cents INT DEFAULT 0 NOT NULL,
            total_cents INT NOT NULL,
            currency VARCHAR(3) DEFAULT \'EUR\' NOT NULL,
            issuer_snapshot JSON NOT NULL,
            customer_snapshot JSON NOT NULL,
            line_items JSON NOT NULL,
            previous_invoice_hash VARCHAR(64) DEFAULT NULL,
            current_hash VARCHAR(64) NOT NULL,
            stripe_payment_intent_id VARCHAR(255) DEFAULT NULL,
            stripe_fee_cents INT DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_INVOICE_UUID (uuid),
            UNIQUE INDEX UNIQ_INVOICE_NUMBER (invoice_number),
            INDEX idx_invoice_number (invoice_number),
            INDEX idx_invoice_series_seq (year, series, sequential_number),
            INDEX idx_invoice_issued_at (issued_at),
            INDEX idx_invoice_status (status),
            INDEX IDX_INVOICE_USER (user_id),
            INDEX IDX_INVOICE_PAYMENT_TX (payment_transaction_id),
            INDEX IDX_INVOICE_ORIGINAL (original_invoice_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_INVOICE_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
            CONSTRAINT FK_INVOICE_PAYMENT_TX FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions (id) ON DELETE SET NULL,
            CONSTRAINT FK_INVOICE_ORIGINAL FOREIGN KEY (original_invoice_id) REFERENCES invoices (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO billing_configs (
            legal_name, tax_id, address, postal_code, city, province, country_code,
            billing_email, default_vat_rate, default_irpf_rate, series_complete, series_simplified, series_rectification,
            next_number_complete, next_number_simplified, next_number_rectification, footer_legal_text, is_active, created_at, updated_at
        ) VALUES (
            'Albert (Registrals)', '00000000T', 'Carrer de la Pau 1', '08001', 'Barcelona', 'Barcelona', 'ES',
            'facturacio@registrals.cat', 21.00, 0.00, 'REG-', 'FS-', 'R-',
            1, 1, 1, 'Règim general de l\'IVA.', 1, NOW(), NOW()
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_INVOICE_USER');
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_INVOICE_PAYMENT_TX');
        $this->addSql('ALTER TABLE invoices DROP FOREIGN KEY FK_INVOICE_ORIGINAL');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('DROP TABLE billing_configs');
    }
}

