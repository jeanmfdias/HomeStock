<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260428122356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL, slug VARCHAR(80) NOT NULL, requires_expiration BOOLEAN NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX categories_slug_unique ON categories (slug)');
        $this->addSql('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(160) NOT NULL, brand VARCHAR(120) DEFAULT NULL, unit_type VARCHAR(8) NOT NULL, quantity NUMERIC(12, 3) NOT NULL, min_stock NUMERIC(12, 3) NOT NULL, expiration_date DATE DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INTEGER NOT NULL, category_id INTEGER NOT NULL, storage_location_id INTEGER DEFAULT NULL, preferred_store_id INTEGER DEFAULT NULL, CONSTRAINT FK_B3BA5A5AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B3BA5A5ACDDD8AF FOREIGN KEY (storage_location_id) REFERENCES storage_locations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B3BA5A5AA654947E FOREIGN KEY (preferred_store_id) REFERENCES stores (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A12469DE2 ON products (category_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5ACDDD8AF ON products (storage_location_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5AA654947E ON products (preferred_store_id)');
        $this->addSql('CREATE INDEX products_user_idx ON products (user_id)');
        $this->addSql('CREATE INDEX products_expiration_idx ON products (expiration_date)');
        $this->addSql('CREATE TABLE stock_movements (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, delta NUMERIC(12, 3) NOT NULL, reason VARCHAR(16) NOT NULL, occurred_at DATETIME NOT NULL, product_id INTEGER NOT NULL, CONSTRAINT FK_A0BE93C94584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX stock_movements_product_idx ON stock_movements (product_id)');
        $this->addSql('CREATE TABLE storage_locations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(80) NOT NULL)');
        $this->addSql('CREATE TABLE stores (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(120) NOT NULL)');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, name VARCHAR(120) NOT NULL, password VARCHAR(255) NOT NULL, roles CLOB NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX users_email_unique ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('DROP TABLE storage_locations');
        $this->addSql('DROP TABLE stores');
        $this->addSql('DROP TABLE users');
    }
}
