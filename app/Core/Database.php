<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Pdo\Mysql;
use Throwable;

/**
 * Akses database via PDO prepared statements (single connection per request).
 *
 * Semua query harus memakai bind parameters — tidak ada string SQL
 * yang dibangun dari input pengguna.
 */
final class Database
{
    private ?PDO $pdo = null;

    private int $transactionLevel = 0;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
            );

            $this->pdo = new PDO($dsn, (string) $this->config['username'], (string) $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                // Konstanta PDO::MYSQL_ATTR_* deprecated sejak PHP 8.5 → pakai Pdo\Mysql (tersedia sejak 8.4).
                Mysql::ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'STRICT_ALL_TABLES'",
            ]);
        }

        return $this->pdo;
    }

    /**
     * Jalankan closure dalam transaksi. Nested transaction didukung lewat
     * savepoint sehingga service bisa memanggil transaksi di dalam transaksi.
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback($this->pdo());
            $this->commitTransaction();
            return $result;
        } catch (Throwable $e) {
            $this->rollBackTransaction();
            throw $e;
        }
    }

    private function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . $this->transactionLevel);
        }
        $this->transactionLevel++;
    }

    private function commitTransaction(): void
    {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->pdo()->commit();
        }
    }

    private function rollBackTransaction(): void
    {
        $this->transactionLevel--;
        if ($this->transactionLevel === 0) {
            $this->pdo()->rollBack();
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . ($this->transactionLevel + 1));
        }
    }
}
