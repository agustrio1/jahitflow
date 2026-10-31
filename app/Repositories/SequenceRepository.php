<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Nomor dokumen otomatis — pengganti MAX(id)+1 (dilarang eksplisit oleh DOC).
 *
 * WAJIB dipanggil di dalam transaksi service: pengambilan nilai memakai
 * SELECT ... FOR UPDATE sehingga dua request bersamaan tidak bisa
 * mendapatkan nomor yang sama. Per bulan memakai counter sendiri
 * (key "SO:202610") agar penomoran mudah dibaca & diaudit.
 */
final class SequenceRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Contoh hasil: "SO-2026-0007".
     */
    public function nextDocumentNumber(string $prefix): string
    {
        $yearMonth = date('Y-m');
        $key = strtoupper($prefix) . ':' . date('Ym');

        $pdo = $this->db->pdo();

        // Baris counter dibuat sekalian bila belum ada; INSERT IGNORE agar
        // transaksi paralel tidak gagal karena duplikat key.
        $insert = $pdo->prepare('INSERT IGNORE INTO sequences (seq_key, next_value) VALUES (?, 1)');
        $insert->execute([$key]);

        $select = $pdo->prepare('SELECT next_value FROM sequences WHERE seq_key = ? FOR UPDATE');
        $select->execute([$key]);
        $value = $select->fetchColumn();

        if ($value === false) {
            throw new RuntimeException("Counter sequensi [{$key}] tidak ditemukan setelah insert.");
        }

        $update = $pdo->prepare('UPDATE sequences SET next_value = next_value + 1 WHERE seq_key = ?');
        $update->execute([$key]);

        return self::format($prefix, $yearMonth, (int) $value);
    }

    /** Pure — mudah diuji tanpa database. */
    public static function format(string $prefix, string $yearMonth, int $number): string
    {
        return sprintf('%s-%s-%04d', strtoupper($prefix), $yearMonth, $number);
    }
}
