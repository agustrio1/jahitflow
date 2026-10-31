<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Tahap per perintah produksi — SNAPSHOT definisi tahap saat perintah dibuat
 * (name & position ikut disimpan supaya riwayat tidak berubah bila definisi
 * diedit kelak). Menyimpan status, target vs realisasi, penanggung jawab,
 * dan waktu mulai/selesai per tahap (§8.E). Baris dihapus cascade bersama
 * perintah produksi.
 */
class CreateProductionOrderStagesTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `production_order_stages` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                production_order_id INT UNSIGNED NOT NULL,
                stage_id INT UNSIGNED NOT NULL,
                stage_name VARCHAR(80) NOT NULL,
                position INT UNSIGNED NOT NULL,
                status ENUM(\'pending\',\'in_progress\',\'done\') NOT NULL DEFAULT \'pending\',
                target_qty INT UNSIGNED NOT NULL DEFAULT 0,
                realized_qty INT UNSIGNED NOT NULL DEFAULT 0,
                assigned_to INT UNSIGNED NULL,
                started_at DATETIME NULL,
                finished_at DATETIME NULL,
                notes VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_pp_stage (production_order_id, stage_id),
                KEY idx_pps_order (production_order_id, position),
                CONSTRAINT fk_pps_order FOREIGN KEY (production_order_id) REFERENCES `production_orders`(id) ON DELETE CASCADE,
                CONSTRAINT fk_pps_stage FOREIGN KEY (stage_id) REFERENCES `production_stages`(id),
                CONSTRAINT fk_pps_assigned FOREIGN KEY (assigned_to) REFERENCES `users`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `production_order_stages`');
    }
}
