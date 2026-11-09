<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add maxYearlyRegistrations to plans and period registration fields to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans ADD max_yearly_registrations INT DEFAULT 20 NOT NULL');
        $this->addSql('ALTER TABLE users ADD period_items_registered INT DEFAULT 0 NOT NULL, ADD period_started_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plans DROP max_yearly_registrations');
        $this->addSql('ALTER TABLE users DROP period_items_registered, DROP period_started_at');
    }
}

