<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Entity pelanggan (§8.B). Data holder murni — query di CustomerRepository.
 */
final class Customer
{
    public const TYPE_PERORANGAN = 'perorangan';
    public const TYPE_PERUSAHAAN = 'perusahaan';

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $contactPerson,
        public readonly string $phone,
        public readonly ?string $email,
        public readonly ?string $address,
        public readonly ?string $city,
        public readonly ?string $notes,
        public readonly bool $isActive,
        public readonly string $createdAt = '',
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            name: (string) $row['name'],
            type: (string) $row['type'],
            contactPerson: $row['contact_person'] ?? null,
            phone: (string) $row['phone'],
            email: $row['email'] ?? null,
            address: $row['address'] ?? null,
            city: $row['city'] ?? null,
            notes: $row['notes'] ?? null,
            isActive: (bool) ($row['is_active'] ?? true),
            createdAt: (string) ($row['created_at'] ?? ''),
        );
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_PERUSAHAAN ? 'Perusahaan' : 'Perorangan';
    }

    /** Baris alamat satu lajur untuk tampilan ringkas. */
    public function addressLine(): string
    {
        $parts = array_filter([$this->address, $this->city]);
        return implode(', ', $parts);
    }
}
