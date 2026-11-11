<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Namirial OnBoarding eIDV traceability and paid KYC credits columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD kyc_reference_id VARCHAR(100) DEFAULT NULL, ADD kyc_last_reason VARCHAR(255) DEFAULT NULL, ADD kyc_paid_credits INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP kyc_reference_id, DROP kyc_last_reason, DROP kyc_paid_credits');
    }
}

