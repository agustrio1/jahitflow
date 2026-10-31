<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Pemeriksaan Quality Control (§8.G DOC). Riwayat pemeriksaan per batch atas
 * hasil sebuah perintah produksi: jumlah diperiksa, lolos, dan gagal, ditambah
 * catatan cacat & tindakan perbaikan. Entri bersifat LOG — sekali tercatat tidak
 * diubah (riwayat tetap utuh untuk audit); koreksi dibuat sebagai pemeriksaan
 * susulan, bukan edit.
 *
 * Aturan numeric ditegakkan di DB sebagai pagar terakhir (§9):
 *  - lolos + gagal TIDAK boleh melebihi jumlah diperiksa;
 *  - hasil 'lolos' berarti nol cacat; 'perbaikan'/'gagal' berarti ada cacat.
 * Validasi bentuk yang lebih ramah tetap di QcInspectionValidator (lapisan aplikasi).
 */
class CreateQcInspectionsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `qc_inspections` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                production_order_id INT UNSIGNED NOT NULL,
                inspected_qty INT UNSIGNED NOT NULL,
                passed_qty INT UNSIGNED NOT NULL DEFAULT 0,
                failed_qty INT UNSIGNED NOT NULL DEFAULT 0,
                result ENUM(\'lolos\',\'perbaikan\',\'gagal\') NOT NULL DEFAULT \'lolos\',
                defect_note VARCHAR(500) NULL,
                rework_action VARCHAR(500) NULL,
                inspected_by INT UNSIGNED NULL,
                inspected_at DATETIME NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_qc_production (production_order_id, created_at),
                KEY idx_qc_result (result),
                CONSTRAINT fk_qc_production FOREIGN KEY (production_order_id) REFERENCES `production_orders`(id),
                CONSTRAINT fk_qc_inspector FOREIGN KEY (inspected_by) REFERENCES `users`(id) ON DELETE SET NULL,
                CONSTRAINT chk_qc_inspected_positive CHECK (inspected_qty > 0),
                CONSTRAINT chk_qc_counts CHECK (passed_qty + failed_qty <= inspected_qty),
                CONSTRAINT chk_qc_result CHECK (
                    (result = \'lolos\' AND failed_qty = 0)
                    OR (result IN (\'perbaikan\',\'gagal\') AND failed_qty > 0)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `qc_inspections`');
    }
}
