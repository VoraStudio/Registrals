<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow authenticated LegacyContact succession claims without prior PDF certificate (nullable source_certificate_id ON DELETE SET NULL)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE succession_claims DROP FOREIGN KEY FK_6E7C79933A5B5358');
        $this->addSql('ALTER TABLE succession_claims CHANGE source_certificate_id source_certificate_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE succession_claims ADD CONSTRAINT FK_6E7C79933A5B5358 FOREIGN KEY (source_certificate_id) REFERENCES certificates (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE succession_claims DROP FOREIGN KEY FK_6E7C79933A5B5358');
        $this->addSql('ALTER TABLE succession_claims CHANGE source_certificate_id source_certificate_id INT NOT NULL');
        $this->addSql('ALTER TABLE succession_claims ADD CONSTRAINT FK_6E7C79933A5B5358 FOREIGN KEY (source_certificate_id) REFERENCES certificates (id) ON DELETE CASCADE');
    }
}

