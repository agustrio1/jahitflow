<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Kategori produk katalog (§8.C DOC).
 */
class CreateProductCategoriesTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `product_categories` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_category_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `product_categories`');
    }
}
