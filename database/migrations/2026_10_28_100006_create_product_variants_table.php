<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Varian produk: kombinasi warna + ukuran + harga satuan (§8.C DOC).
 * Harga per varian — sales order menghitung dari sini, bukan dari input browser.
 */
class CreateProductVariantsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `product_variants` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                color VARCHAR(60) NOT NULL,
                size VARCHAR(20) NOT NULL,
                unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
                sku VARCHAR(60) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_variant (product_id, color, size),
                KEY idx_variants_sku (sku),
                CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES `products`(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `product_variants`');
    }
}
