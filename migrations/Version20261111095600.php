<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111095600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add extraCertificatePriceCents, kycIncluded and kycPriceCents to plans table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans ADD extra_certificate_price_cents INT DEFAULT 590 NOT NULL, ADD kyc_included INT DEFAULT 0 NOT NULL, ADD kyc_price_cents INT DEFAULT 350 NOT NULL');
        $this->addSql("UPDATE plans SET extra_certificate_price_cents = 590, kyc_included = 0, kyc_price_cents = 350 WHERE code = 'free'");
        $this->addSql("UPDATE plans SET extra_certificate_price_cents = 590, kyc_included = 1, kyc_price_cents = 350 WHERE code = 'plus'");
        $this->addSql("UPDATE plans SET extra_certificate_price_cents = 0, kyc_included = 9999, kyc_price_cents = 0 WHERE code = 'unlimited'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans DROP extra_certificate_price_cents, DROP kyc_included, DROP kyc_price_cents');
    }
}
