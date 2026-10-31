<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\User;
use PDO;

/**
 * Akses data pengguna. Semua query prepared statement; tidak ada
 * string SQL yang dibangun dari input.
 */
final class UserRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM `users` WHERE email = ? LIMIT 1');
        $stmt->execute([mb_strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : User::fromRow($row);
    }

    public function find(int $id): ?User
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM `users` WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : User::fromRow($row);
    }

    private function pdo(): PDO
    {
        return $this->db->pdo();
    }
}
