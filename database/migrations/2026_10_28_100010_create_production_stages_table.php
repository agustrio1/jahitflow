<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * DEFINISI tahap produksi (§8.E). Sengaja dibuat tabel data (bukan enum
 * hardcoded) agar tahapan "dapat disesuaikan tanpa merombak seluruh sistem".
 * Baris aktif di-snapshot ke production_order_stages saat perintah dibuat,
 * sehingga perubahan definisi berikutnya tidak merusak riwayat pekerjaan
 * yang sedang berjalan.
 */
class CreateProductionStagesTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `production_stages` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(80) NOT NULL,
                position INT UNSIGNED NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_stage_position (position),
                KEY idx_stage_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `production_stages`');
    }
}
