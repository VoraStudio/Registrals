<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120190300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create service_provider_configs table with historical validity dates and seed initial providers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE service_provider_configs (id INT AUTO_INCREMENT NOT NULL, category VARCHAR(30) NOT NULL, provider_code VARCHAR(60) NOT NULL, public_name VARCHAR(120) NOT NULL, valid_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', valid_to DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_spc_category_dates (category, valid_from, valid_to), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO service_provider_configs (category, provider_code, public_name, valid_from, valid_to, notes, created_at) VALUES
            ('ai', 'sglang', 'Assistència de Lectura Sobirana', '2026-01-01 00:00:00', NULL, 'Model de visió i OCR en node privat local (sense exposar el nom intern del motor).', NOW()),
            ('payment', 'stripe', 'Stripe', '2026-01-01 00:00:00', NULL, 'Passarel·la de pagament i subscripcions.', NOW()),
            ('kyc', 'namirial', 'Namirial eIDV', '2026-01-01 00:00:00', NULL, 'Verificació d\'identitat documental i biomètrica.', NOW()),
            ('blockchain', 'polygon-pos', 'Polygon PoS (Layer 2)', '2026-01-01 00:00:00', NULL, 'Ancoratge criptogràfic de fingerprints SHA-256 en calldata.', NOW()),
            ('tsp', 'signaturit_mock', 'QTSP eIDAS (Sandbox)', '2026-01-01 00:00:00', NULL, 'Segellat de temps qualificat RFC 3161 (pendent de selecció/activació de QTSP final).', NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE service_provider_configs');
    }
}

