<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ES and EN media columns to ad_campaigns with CAT as default';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ad_campaigns ADD media_path_es VARCHAR(255) DEFAULT NULL, ADD media_type_es VARCHAR(20) DEFAULT NULL, ADD media_path_en VARCHAR(255) DEFAULT NULL, ADD media_type_en VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ad_campaigns DROP media_path_es, DROP media_type_es, DROP media_path_en, DROP media_type_en');
    }
}

