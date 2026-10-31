<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Bahan baku / inventaris (§8.F DOC). Katalog bahan terpisah dari katalog
 * produk jual — "jangan mencampur katalog produk dengan stok bahan baku".
 *
 * current_stock adalah kolom DENORMALIZED (cache saldo) yang SELALU ditulis
 * ulang dari ledger stock_movements di dalam transaksi (SELECT ... FOR UPDATE),
 * sehingga baca daftar stok cepat tanpa menjumlah riwayat. CHECK (current_stock
 * >= 0) menjadi pagar terakhir agar stok tidak bisa negatif meski ada bug.
 */
class CreateRawMaterialsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `raw_materials` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(30) NOT NULL,
                name VARCHAR(150) NOT NULL,
                type ENUM(\'bahan_utama\',\'bahan_penolong\',\'packaging\') NOT NULL DEFAULT \'bahan_utama\',
                unit VARCHAR(20) NOT NULL,
                current_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
                min_stock DECIMAL(12,3) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_material_code (code),
                KEY idx_material_active (is_active),
                KEY idx_material_type (type),
                CONSTRAINT chk_material_stock_nonneg CHECK (current_stock >= 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `raw_materials`');
    }
}
