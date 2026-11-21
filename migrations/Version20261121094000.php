<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121094000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ad_campaigns and faq_items tables and import existing FAQs from faqs.ca.json';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ad_campaigns (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, description LONGTEXT NOT NULL, media_path VARCHAR(255) NOT NULL, media_type VARCHAR(20) DEFAULT \'image\' NOT NULL, duration_seconds INT DEFAULT 8 NOT NULL, starts_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_active TINYINT(1) DEFAULT 1 NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_ad_active_dates (is_active, starts_at, ends_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE faq_items (id INT AUTO_INCREMENT NOT NULL, question VARCHAR(255) NOT NULL, answer LONGTEXT NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, sort_order INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_faq_active_order (is_active, sort_order), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function postUp(Schema $schema): void
    {
        $jsonPath = dirname(__DIR__) . '/content/faq/faqs.ca.json';
        if (!is_file($jsonPath)) {
            return;
        }

        $decoded = json_decode((string) file_get_contents($jsonPath), true);
        if (!is_array($decoded)) {
            return;
        }

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $order = 10;
        foreach ($decoded as $row) {
            $q = trim((string) ($row['question'] ?? ''));
            $a = trim((string) ($row['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $this->connection->insert('faq_items', [
                'question' => $q,
                'answer' => $a,
                'is_active' => 1,
                'sort_order' => $order,
                'created_at' => $now,
            ]);
            $order += 10;
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE ad_campaigns');
        $this->addSql('DROP TABLE faq_items');
    }
}
