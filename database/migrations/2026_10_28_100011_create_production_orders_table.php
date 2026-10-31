<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Perintah produksi berdasarkan pesanan yang disetujui (§8.E). Relasi
 * 1:1 dengan sales_orders yang sudah dikonfirmasi (UNIQUE mencegah perintah
 * ganda). target_quantity = total pcs dari item pesanan. Gerbang DP ≥30%
 * ditegakkan service saat pembuatan (§9: produksi tak mulai sebelum
 * persetujuan terpenuhi). Perubahan status penting tercatat di audit_logs.
 */
class CreateProductionOrdersTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `production_orders` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_number VARCHAR(30) NOT NULL,
                sales_order_id INT UNSIGNED NOT NULL,
                status ENUM(\'queued\',\'running\',\'completed\',\'cancelled\') NOT NULL DEFAULT \'queued\',
                target_quantity INT UNSIGNED NOT NULL DEFAULT 0,
                target_date DATE NULL,
                assigned_to INT UNSIGNED NULL,
                description VARCHAR(255) NULL,
                notes TEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_pp_number (document_number),
                UNIQUE KEY uniq_pp_sales_order (sales_order_id),
                KEY idx_pp_status (status),
                KEY idx_pp_assigned (assigned_to),
                CONSTRAINT fk_pp_sales_order FOREIGN KEY (sales_order_id) REFERENCES `sales_orders`(id),
                CONSTRAINT fk_pp_assigned FOREIGN KEY (assigned_to) REFERENCES `users`(id) ON DELETE SET NULL,
                CONSTRAINT fk_pp_creator FOREIGN KEY (created_by) REFERENCES `users`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `production_orders`');
    }
}
