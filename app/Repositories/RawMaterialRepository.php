<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\RawMaterial;
use PDO;

/**
 * Akses data master bahan baku (§8.F). Daftar memuat current_stock (cache
 * saldo). Untuk mengubah saldo, service memakai lockStock() (SELECT ... FOR
 * UPDATE) di DALAM transaksi agar dua pemakaian bersamaan tidak saling
 * menimpa sampai stok negatif (§9 DOC: transaksi stok dilindungi dari race).
 */
final class RawMaterialRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{list: list<RawMaterial>, total: int}
     */
    public function paginate(string $search = '', string $type = 'semua', string $status = 'aktif', bool $lowOnly = false, int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $type, $status, $lowOnly);
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $countStmt = $this->db->pdo()->prepare("SELECT COUNT(*) FROM raw_materials {$where}");
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->pdo()->prepare(
            "SELECT * FROM raw_materials {$where} ORDER BY name ASC LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($bind);

        return [
            'list' => array_map(static fn(array $r): RawMaterial => RawMaterial::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
        ];
    }

    public function find(int $id): ?RawMaterial
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM raw_materials WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : RawMaterial::fromRow($row);
    }

    public function codeExists(string $code, int $exceptId = 0): bool
    {
        $stmt = $this->db->pdo()->prepare('SELECT COUNT(*) FROM raw_materials WHERE code = ? AND id <> ?');
        $stmt->execute([strtoupper(trim($code)), $exceptId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** Bahan aktif untuk dropdown pencatatan pemakaian. */
    public function allActive(): array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM raw_materials WHERE is_active = 1 ORDER BY name ASC');
        $stmt->execute();

        return array_map(static fn(array $r): RawMaterial => RawMaterial::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Kunci baris & baca saldo terkini. WAJIB dipanggil dalam transaksi.
     * Mengembalikan null bila bahan tidak ada.
     */
    public function lockStock(int $id): ?string
    {
        $stmt = $this->db->pdo()->prepare('SELECT current_stock FROM raw_materials WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $stock = $stmt->fetchColumn();

        return $stock === false ? null : (string) $stock;
    }

    /** Tulis ulang cache saldo (dari hasil ledger). */
    public function setStock(int $id, string $newStock): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE raw_materials SET current_stock = ? WHERE id = ?');
        $stmt->execute([$newStock, $id]);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO raw_materials (code, name, type, unit, current_stock, min_stock, is_active, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($this->bindPayload($data));

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE raw_materials
             SET code = ?, name = ?, type = ?, unit = ?, min_stock = ?, is_active = ?, notes = ?
             WHERE id = ?'
        );
        // current_stock TIDAK diubah di sini — hanya lewat ledger StockService.
        $stmt->execute([
            strtoupper(trim((string) $data['code'])),
            trim((string) $data['name']),
            (string) $data['type'],
            trim((string) $data['unit']),
            $this->numeric($data['min_stock'] ?? '0'),
            !array_key_exists('is_active', $data) || $data['is_active'] ? 1 : 0,
            isset($data['notes']) && $data['notes'] !== '' ? trim((string) $data['notes']) : null,
            $id,
        ]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE raw_materials SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function bindPayload(array $data): array
    {
        return [
            strtoupper(trim((string) $data['code'])),
            trim((string) $data['name']),
            (string) $data['type'],
            trim((string) $data['unit']),
            '0.000', // stok awal SELALU nol — penerimaan dicatat via ledger
            $this->numeric($data['min_stock'] ?? '0'),
            !array_key_exists('is_active', $data) || $data['is_active'] ? 1 : 0,
            isset($data['notes']) && $data['notes'] !== '' ? trim((string) $data['notes']) : null,
        ];
    }

    private function numeric(mixed $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? '0.000' : bcadd($trimmed, '0', 3);
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, string $type, string $status, bool $lowOnly): array
    {
        $conditions = [];
        $bind = [];

        if ($status === 'aktif') {
            $conditions[] = 'is_active = 1';
        } elseif ($status === 'nonaktif') {
            $conditions[] = 'is_active = 0';
        }

        if ($type !== 'semua' && $type !== '') {
            $conditions[] = 'type = ?';
            $bind[] = $type;
        }

        if ($lowOnly) {
            $conditions[] = 'current_stock <= min_stock';
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(code LIKE ? OR name LIKE ?)';
            $bind[] = $like;
            $bind[] = $like;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $bind];
    }
}
