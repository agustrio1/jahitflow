<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Pencatatan percobaan login untuk throttling (§12 DOC).
 * Satu baris per email; naive (tanpa distributed lock) — cukup untuk
 * aplikasi single-tenant di VPS sendiri.
 */
final class LoginAttemptRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array{attempts:int, locked_until:?string}|null */
    public function find(string $email): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT attempts, locked_until FROM login_attempts WHERE email = ? LIMIT 1');
        $stmt->execute([mb_strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ['attempts' => (int) $row['attempts'], 'locked_until' => $row['locked_until'] === null ? null : (string) $row['locked_until']];
    }

    public function isLocked(string $email): bool
    {
        $record = $this->find($email);
        if ($record === null || $record['locked_until'] === null) {
            return false;
        }

        return strtotime($record['locked_until']) > time();
    }

    /** Menambah hitungan gagal; mengunci saat melewati ambang. */
    public function recordFailure(string $email, ?string $ip, int $maxAttempts, int $lockMinutes): void
    {
        $email = mb_strtolower(trim($email));
        $now = time();

        $existing = $this->find($email);
        $attempts = ($existing['attempts'] ?? 0) + 1;
        $lockedUntil = $attempts >= $maxAttempts
            ? date('Y-m-d H:i:s', $now + $lockMinutes * 60)
            : null;

        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO login_attempts (email, attempts, locked_until, last_ip)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts), locked_until = VALUES(locked_until), last_ip = VALUES(last_ip)'
        );
        $stmt->execute([$email, $attempts, $lockedUntil, $ip]);
    }

    public function clear(string $email): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM login_attempts WHERE email = ?');
        $stmt->execute([mb_strtolower(trim($email))]);
    }

    /** Sisa detik kunci, 0 bila tidak terkunci. */
    public function secondsRemaining(string $email): int
    {
        $record = $this->find($email);
        if ($record === null || $record['locked_until'] === null) {
            return 0;
        }
        $remain = strtotime($record['locked_until']) - time();

        return $remain > 0 ? $remain : 0;
    }
}
