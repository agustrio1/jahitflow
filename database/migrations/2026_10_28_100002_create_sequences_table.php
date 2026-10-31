<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Penghitung nomor dokumen otomatis. Menggantikan pola MAX(id)+1 yang
 * dilarang eksplisit (§ aturan bisnis DOC). Nomor diambil dengan
 * SELECT ... FOR UPDATE di dalam transaksi — aman dari race.
 */
class CreateSequencesTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `sequences` (
                seq_key VARCHAR(40) NOT NULL PRIMARY KEY,
                next_value BIGINT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sequences`');
    }
}
