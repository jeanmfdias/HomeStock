<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Per-batch inventory refactor: introduce `batches` table, drop scalar
 * `quantity`/`expiration_date` from `products`, and re-anchor `stock_movements`
 * to a batch instead of a product. Existing stock data is wiped.
 */
final class Version20260503120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Per-batch inventory refactor: add batches table, move stock movements to batches, drop product.quantity/expiration_date.';
    }

    public function up(Schema $schema): void
    {
        // 1) Create batches table.
        $this->addSql('CREATE TABLE batches (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            product_id INTEGER NOT NULL,
            quantity NUMERIC(12, 3) NOT NULL,
            expiration_date DATE DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            CONSTRAINT FK_batches_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX batches_product_idx ON batches (product_id)');
        $this->addSql('CREATE INDEX batches_expiration_idx ON batches (expiration_date)');
        $this->addSql('CREATE INDEX batches_product_expiration_idx ON batches (product_id, expiration_date)');

        // 2) Drop and recreate stock_movements anchored on batch_id (wipe).
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('CREATE TABLE stock_movements (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            batch_id INTEGER NOT NULL,
            delta NUMERIC(12, 3) NOT NULL,
            reason VARCHAR(16) NOT NULL,
            occurred_at DATETIME NOT NULL,
            CONSTRAINT FK_stock_movements_batch FOREIGN KEY (batch_id) REFERENCES batches (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX stock_movements_batch_idx ON stock_movements (batch_id)');

        // 3) Rebuild products without quantity / expiration_date (SQLite ALTER limits).
        $this->addSql('DROP INDEX IF EXISTS products_expiration_idx');
        $this->addSql('CREATE TABLE products_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR(160) NOT NULL,
            brand VARCHAR(120) DEFAULT NULL,
            unit_type VARCHAR(8) NOT NULL,
            min_stock NUMERIC(12, 3) NOT NULL,
            notes CLOB DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            user_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            storage_location_id INTEGER DEFAULT NULL,
            preferred_store_id INTEGER DEFAULT NULL,
            CONSTRAINT FK_B3BA5A5AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_B3BA5A5A12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_B3BA5A5ACDDD8AF FOREIGN KEY (storage_location_id) REFERENCES storage_locations (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_B3BA5A5AA654947E FOREIGN KEY (preferred_store_id) REFERENCES stores (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('INSERT INTO products_new (id, name, brand, unit_type, min_stock, notes, created_at, updated_at, user_id, category_id, storage_location_id, preferred_store_id)
            SELECT id, name, brand, unit_type, min_stock, notes, created_at, updated_at, user_id, category_id, storage_location_id, preferred_store_id FROM products');
        $this->addSql('DROP TABLE products');
        $this->addSql('ALTER TABLE products_new RENAME TO products');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A12469DE2 ON products (category_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5ACDDD8AF ON products (storage_location_id)');
        $this->addSql('CREATE INDEX IDX_B3BA5A5AA654947E ON products (preferred_store_id)');
        $this->addSql('CREATE INDEX products_user_idx ON products (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('per-batch refactor is one-way');
    }
}
