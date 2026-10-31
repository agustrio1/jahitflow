<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base untuk file migration di database/migrations.
 *
 * Migration menuliskan DDL SQL eksplisit (CREATE/ALTER/DROP). DDL dieksekusi
 * apa adanya —integritas nama tabel/kolom adalah tanggung jawab developer,
 * BUKAN input pengguna, jadi tidak ada risiko injection dari sisi runtime.
 *
 * Catatan MySQL: DDL menyebabkan implicit commit, sehingga TIDAK bisa
 * di-rollback dalam transaksi. MigrationRunner karena itu memproses satu
 * per satu dan berhenti pada kegagalan pertama (bukan all-or-nothing).
 */
abstract class Migration
{
    public function __construct(protected PDO $pdo)
    {
    }

    abstract public function up(): void;

    abstract public function down(): void;

    protected function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }

    protected function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    protected function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
        );
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
