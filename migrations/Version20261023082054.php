<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023082054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categories (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, slug VARCHAR(120) NOT NULL, description LONGTEXT DEFAULT NULL, icon VARCHAR(60) DEFAULT NULL, sort_order INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, custom_field_definitions JSON DEFAULT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_3AF34668989D9B62 (slug), INDEX idx_categories_slug (slug), INDEX idx_categories_parent_active (parent_id, is_active), INDEX IDX_3AF34668727ACA70 (parent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_documents (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, type VARCHAR(35) NOT NULL, file_path VARCHAR(255) NOT NULL, original_filename VARCHAR(150) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size_bytes BIGINT NOT NULL, sha256_hash VARCHAR(64) NOT NULL, ai_metadata JSON DEFAULT NULL, created_at DATETIME NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_AE6A6478D17F50A6 (uuid), INDEX idx_doc_item_type (item_id, type), INDEX idx_doc_sha256 (sha256_hash), INDEX IDX_AE6A6478126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE items (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, cui VARCHAR(32) NOT NULL, is_collection TINYINT DEFAULT 0 NOT NULL, name VARCHAR(200) NOT NULL, brand VARCHAR(100) DEFAULT NULL, model VARCHAR(100) DEFAULT NULL, serial_number VARCHAR(150) DEFAULT NULL, declared_value_cents BIGINT DEFAULT NULL, acquisition_date DATE DEFAULT NULL, acquisition_type VARCHAR(30) NOT NULL, acquisition_circumstances LONGTEXT DEFAULT NULL, custom_attributes JSON DEFAULT NULL, status VARCHAR(30) DEFAULT \'active\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INT NOT NULL, category_id INT NOT NULL, subcategory_id INT DEFAULT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_E11EE94DD17F50A6 (uuid), UNIQUE INDEX UNIQ_E11EE94DD3F6F824 (cui), INDEX idx_items_cui (cui), INDEX idx_items_owner_status (owner_id, status), INDEX idx_items_serial (serial_number), INDEX idx_items_parent (parent_id), INDEX idx_items_category (category_id), INDEX IDX_E11EE94D7E3C61F9 (owner_id), INDEX IDX_E11EE94D5DC6FE57 (subcategory_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_documents ADD CONSTRAINT FK_AE6A6478126F525E FOREIGN KEY (item_id) REFERENCES items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94D12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94D5DC6FE57 FOREIGN KEY (subcategory_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE items ADD CONSTRAINT FK_E11EE94D727ACA70 FOREIGN KEY (parent_id) REFERENCES items (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE item_documents DROP FOREIGN KEY FK_AE6A6478126F525E');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94D7E3C61F9');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94D12469DE2');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94D5DC6FE57');
        $this->addSql('ALTER TABLE items DROP FOREIGN KEY FK_E11EE94D727ACA70');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE item_documents');
        $this->addSql('DROP TABLE items');
    }
}
