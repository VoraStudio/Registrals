<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Add legal terms acceptance fields to users table (Iteració 8 / Blindatge Jurídic)
 */
final class Version20261105235700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Afegeix camps d\'acceptació forense de clàusules legals i IP de registre a la taula users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD legal_terms_accepted_at DATETIME DEFAULT NULL, ADD legal_terms_version VARCHAR(20) DEFAULT NULL, ADD registration_ip VARCHAR(45) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP legal_terms_accepted_at, DROP legal_terms_version, DROP registration_ip');
    }
}
