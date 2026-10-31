<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Produk katalog (§8.C DOC). Katalog produk TIDAK sama dengan stok
 * bahan baku — harga berada di varian; spesifikasi bahan & custom di sini.
 */
class CreateProductsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `products` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id INT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                garment_type VARCHAR(80) NULL,
                description TEXT NULL,
                material_spec VARCHAR(255) NULL,
                custom_spec TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_products_category (category_id),
                KEY idx_products_name (name),
                CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES `product_categories`(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `products`');
    }
}
