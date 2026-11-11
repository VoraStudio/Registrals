<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111115500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification, optional 2FA flag, and hashed 6-digit email OTP columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email_verified_at DATETIME DEFAULT NULL, ADD two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD email_otp_hash VARCHAR(64) DEFAULT NULL, ADD email_otp_purpose VARCHAR(30) DEFAULT NULL, ADD email_otp_expires_at DATETIME DEFAULT NULL, ADD email_otp_sent_at DATETIME DEFAULT NULL, ADD email_otp_attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE users SET email_verified_at = COALESCE(legal_terms_accepted_at, created_at) WHERE email_verified_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP email_verified_at, DROP two_factor_enabled, DROP email_otp_hash, DROP email_otp_purpose, DROP email_otp_expires_at, DROP email_otp_sent_at, DROP email_otp_attempts');
    }
}

