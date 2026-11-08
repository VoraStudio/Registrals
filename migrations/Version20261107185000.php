<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add downgrade_grace_period_expires_at to users table
 */
final class Version20261107185000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Afegeix camp downgrade_grace_period_expires_at a la taula users per a la gestió del període de gràcia en el downgrade a Pla Free';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD downgrade_grace_period_expires_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP downgrade_grace_period_expires_at');
    }
}

