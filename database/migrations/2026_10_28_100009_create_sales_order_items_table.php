<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Item pesanan (§8.D): kuantitas per varian warna/ukuran. unit_price
 * adalah SNAPSHOT harga varian saat pesanan dibuat — agar perubahan harga
 * katalog berikutnya tidak mengubah nilai pesanan lama. line_total dihitung
 * server (quantity x unit_price). Satu varian tidak boleh dobel per pesanan
 * (unique). Baris dihapus cascade saat pesanan draft dihapus.
 */
class CreateSalesOrderItemsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `sales_order_items` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sales_order_id INT UNSIGNED NOT NULL,
                product_variant_id INT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                unit_price DECIMAL(14,2) NOT NULL,
                line_total DECIMAL(14,2) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_so_variant (sales_order_id, product_variant_id),
                KEY idx_so_items_order (sales_order_id),
                CONSTRAINT fk_so_items_order FOREIGN KEY (sales_order_id) REFERENCES `sales_orders`(id) ON DELETE CASCADE,
                CONSTRAINT fk_so_items_variant FOREIGN KEY (product_variant_id) REFERENCES `product_variants`(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sales_order_items`');
    }
}
