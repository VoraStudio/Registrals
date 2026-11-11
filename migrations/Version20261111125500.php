<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111125500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create site_visits table for cookieless RGPD-compliant geographic web telemetry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_visits (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, country_code VARCHAR(2) NOT NULL, ip_hash VARCHAR(64) NOT NULL, device_type VARCHAR(20) DEFAULT \'desktop\' NOT NULL, created_at DATETIME NOT NULL, INDEX idx_site_visits_country (country_code), INDEX idx_site_visits_created (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_visits');
    }
}
