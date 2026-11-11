<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add hashed password reset token and expiration timestamp to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD reset_password_token_hash VARCHAR(64) DEFAULT NULL, ADD reset_password_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_users_reset_token ON users (reset_password_token_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_reset_token ON users');
        $this->addSql('ALTER TABLE users DROP reset_password_token_hash, DROP reset_password_expires_at');
    }
}

