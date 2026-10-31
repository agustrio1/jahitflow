<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Customer;
use PDO;

/**
 * Akses data pelanggan. Prepared statements semua; pencarian lewat
 * LIKE dengan parameter (wildcard di-escape, bukan konkatenasi).
 */
final class CustomerRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Daftar + jumlah total untuk pagination.
     *
     * @return array{list: list<Customer>, total: int}
     */
    public function paginate(string $search = '', string $status = 'aktif', int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $status);

        $countStmt = $this->db->pdo()->prepare("SELECT COUNT(*) FROM customers {$where}");
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM customers {$where} ORDER BY name ASC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($bind);

        return [
            'list' => array_map(static fn(array $r): Customer => Customer::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
        ];
    }

    public function find(int $id): ?Customer
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : Customer::fromRow($row);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO customers (name, type, contact_person, phone, email, address, city, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($this->bindPayload($data));

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE customers
             SET name = ?, type = ?, contact_person = ?, phone = ?, email = ?, address = ?, city = ?, notes = ?
             WHERE id = ?'
        );
        $stmt->execute([...$this->bindPayload($data), $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE customers SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    /** Semua pelanggan aktif untuk dropdown form pesanan (bukan API publik). */
    public function allActive(): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM customers WHERE is_active = 1 ORDER BY name ASC');
        $stmt->execute();

        return array_map(static fn(array $r): Customer => Customer::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private function bindPayload(array $data): array
    {
        return [
            trim((string) $data['name']),
            (string) $data['type'],
            isset($data['contact_person']) && $data['contact_person'] !== '' ? (string) $data['contact_person'] : null,
            trim((string) $data['phone']),
            isset($data['email']) && $data['email'] !== '' ? (string) $data['email'] : null,
            isset($data['address']) && $data['address'] !== '' ? (string) $data['address'] : null,
            isset($data['city']) && $data['city'] !== '' ? (string) $data['city'] : null,
            isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, string $status): array
    {
        $conditions = [];
        $bind = [];

        if ($status === 'aktif') {
            $conditions[] = 'is_active = 1';
        } elseif ($status === 'nonaktif') {
            $conditions[] = 'is_active = 0';
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(name LIKE ? OR phone LIKE ? OR city LIKE ?)';
            $bind = [$like, $like, $like];
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $bind];
    }
}
