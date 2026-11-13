<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113151500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fiscal billing address fields to users table for self-issued invoices';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD billing_name VARCHAR(255) DEFAULT NULL, ADD billing_address VARCHAR(255) DEFAULT NULL, ADD billing_postal_code VARCHAR(20) DEFAULT NULL, ADD billing_city VARCHAR(100) DEFAULT NULL, ADD billing_province VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP billing_name, DROP billing_address, DROP billing_postal_code, DROP billing_city, DROP billing_province');
    }
}

