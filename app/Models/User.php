<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Entity pengguna. Data holder murni — query ada di UserRepository.
 *
 * `password` menyimpan HASH (bukan plaintext) dan tidak boleh dirender
 * ke view. Diisi hanya saat kebutuhan verifikasi login.
 */
final class User
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_OPERATOR = 'operator';
    public const ROLE_KEUANGAN = 'keuangan';

    private const ROLE_LABELS = [
        self::ROLE_ADMIN => 'Administrator',
        self::ROLE_MANAGER => 'Manajer',
        self::ROLE_OPERATOR => 'Operator Produksi',
        self::ROLE_KEUANGAN => 'Keuangan',
    ];

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $isActive = true,
        public readonly ?string $password = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            email: (string) $row['email'],
            role: (string) $row['role'],
            isActive: (bool) ($row['is_active'] ?? true),
            password: isset($row['password']) ? (string) $row['password'] : null,
        );
    }

    /** @param string|list<string> $roles */
    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst($this->role);
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
        $second = isset($parts[1]) ? mb_strtoupper(mb_substr($parts[1], 0, 1)) : '';
        return $first . $second;
    }
}
