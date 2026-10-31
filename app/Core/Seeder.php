<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base untuk file seeder di database/seeders.
 *
 * Seeder hanya berisi DATA DEMO FIKTIF (aturan §7 DOC). Gunakan helper
 * insert()/truncate() agar penulisan konsisten dan prepared statement terjaga.
 *
 * Idempotensi: gunakan truncate() di awal run() bila seeder dirancang
 * untuk dijalankan berulang (defaultDemoSeeder melakukan ini).
 */
abstract class Seeder
{
    public function __construct(protected PDO $pdo)
    {
    }

    abstract public function run(): void;

    /**
     * Insert satu baris. Column di-whitelist oleh pemanggil; nama kolom
     * dibungkus backtick, nilai selalu lewat placeholder.
     *
     * @param array<string, mixed> $values column => value
     */
    protected function insert(string $table, array $values): int
    {
        $columns = array_keys($values);
        $colSql = implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $stmt = $this->pdo->prepare("INSERT INTO `{$table}` ({$colSql}) VALUES ({$placeholders})");
        $stmt->execute(array_values($values));

        return (int) $this->pdo->lastInsertId();
    }

    /** Kosongkan tabel dan reset auto-increment (untuk seeder idempoten). */
    protected function truncate(string $table): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec("TRUNCATE TABLE `{$table}`");
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Insert hanya bila belum ada baris dengan kondisi ini — menjaga
     * idempotensi tanpa truncate (mis. data referensi master).
     *
     * @param array<string, mixed> $where column => value
     * @param array<string, mixed> $values
     */
    protected function insertIfMissing(string $table, array $where, array $values): void
    {
        $conds = implode(' AND ', array_map(static fn(string $c): string => '`' . $c . '` = ?', array_keys($where)));
        $check = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE {$conds}");
        $check->execute(array_values($where));

        if ((int) $check->fetchColumn() === 0) {
            $this->insert($table, $values);
        }
    }
}
