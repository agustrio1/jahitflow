<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Ledger pergerakan stok bahan (§8.F DOC: riwayat + penerimaan + pemakaian +
 * penyesuaian beralasan). Setiap baris mencatat saldo sebelum/sesudah agar
 * riwayat dapat diaudit dan saldo current_stock bahan dapat direkonstruksi.
 *
 * Arah:
 *  - in         → penerimaan (pembelian/retur); quantity > 0.
 *  - out        → pemakaian (produksi); quantity > 0; saldo tidak boleh negatif.
 *  - adjustment → penyesuaian (selisih stock opname, dsb); quantity boleh +/-.
 *
 * Rujukan polymorphic (reference_type + reference_id) menautkan pemakaian ke
 * perintah produksi tanpa FK keras — §9: stok berkurang HANYA pada peristiwa
 * pemakaian, bukan saat pesanan dibuat.
 */
class CreateStockMovementsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `stock_movements` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                raw_material_id INT UNSIGNED NOT NULL,
                direction ENUM(\'in\',\'out\',\'adjustment\') NOT NULL,
                quantity DECIMAL(12,3) NOT NULL,
                balance_before DECIMAL(12,3) NOT NULL,
                balance_after DECIMAL(12,3) NOT NULL,
                reason VARCHAR(190) NOT NULL,
                reference_type VARCHAR(40) NULL,
                reference_id INT UNSIGNED NULL,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sm_material (raw_material_id, created_at),
                KEY idx_sm_reference (reference_type, reference_id),
                CONSTRAINT fk_sm_material FOREIGN KEY (raw_material_id) REFERENCES `raw_materials`(id),
                CONSTRAINT fk_sm_creator FOREIGN KEY (created_by) REFERENCES `users`(id) ON DELETE SET NULL,
                CONSTRAINT chk_sm_balance_nonneg CHECK (balance_after >= 0),
                CONSTRAINT chk_sm_quantity CHECK (
                    (direction IN (\'in\',\'out\') AND quantity > 0)
                    OR (direction = \'adjustment\' AND quantity <> 0)
                )
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `stock_movements`');
    }
}
