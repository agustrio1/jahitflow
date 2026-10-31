<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Product;
use App\Models\ProductVariant;
use PDO;

/**
 * Akses agregat produk: produk + varian + foto. Varian untuk daftar
 * halaman ini dimuat SEKALI lewat IN (...) — bukan query per baris
 * (§7 DOC: hindari N+1).
 */
final class ProductRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{list: list<Product>, total: int}
     */
    public function paginate(string $search = '', int $categoryId = 0, string $status = 'aktif', int $offset = 0, int $limit = 15): array
    {
        [$where, $bind] = $this->buildFilter($search, $categoryId, $status);

        $countStmt = $this->db->pdo()->prepare("SELECT COUNT(*) FROM products p {$where}");
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT p.*, c.name AS category_name FROM products p
                JOIN product_categories c ON c.id = p.category_id '
            . $where . ' ORDER BY p.name ASC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($bind);
        $products = array_map(static fn(array $r): Product => Product::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));

        return ['list' => $this->attachVariants($products), 'total' => $total];
    }

    public function find(int $id): ?Product
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT p.*, c.name AS category_name FROM products p
             JOIN product_categories c ON c.id = p.category_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->attachVariants([Product::fromRow($row)])[0]
            ->withPhotos($this->photosFor($id));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO products (category_id, name, garment_type, description, material_spec, custom_spec)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute($this->bindPayload($data));

        return (int) $this->db->pdo()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $stmt = $this->db->pdo()->prepare(
            'UPDATE products
             SET category_id = ?, name = ?, garment_type = ?, description = ?, material_spec = ?, custom_spec = ?
             WHERE id = ?'
        );
        $stmt->execute([...$this->bindPayload($data), $id]);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE products SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    // ------------------------------------------------------------------
    // Varian
    // ------------------------------------------------------------------

    public function variantComboExists(int $productId, string $color, string $size): bool
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT COUNT(*) FROM product_variants WHERE product_id = ? AND color = ? AND size = ?'
        );
        $stmt->execute([$productId, $color, $size]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /** @param array<string, mixed> $data */
    public function addVariant(int $productId, array $data): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO product_variants (product_id, color, size, unit_price, sku) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $productId,
            trim((string) $data['color']),
            trim((string) $data['size']),
            (float) $data['unit_price'],
            ($data['sku'] ?? '') !== '' ? trim((string) $data['sku']) : null,
        ]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function findVariant(int $id): ?ProductVariant
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM product_variants WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ProductVariant::fromRow($row);
    }

    public function updateVariantPrice(int $id, float $unitPrice): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE product_variants SET unit_price = ? WHERE id = ?');
        $stmt->execute([$unitPrice, $id]);
    }

    public function setVariantActive(int $id, bool $active): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE product_variants SET is_active = ? WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    // ------------------------------------------------------------------
    // Foto referensi
    // ------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function photosFor(int $productId): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT id, product_id, file_path, original_name, mime, size_bytes, created_at
             FROM product_photos WHERE product_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$productId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findPhoto(int $photoId): ?array
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM product_photos WHERE id = ? LIMIT 1');
        $stmt->execute([$photoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function addPhoto(int $productId, string $filePath, string $originalName, string $mime, int $size, ?int $userId): int
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO product_photos (product_id, file_path, original_name, mime, size_bytes, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$productId, $filePath, $originalName, $mime, $size, $userId]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function deletePhoto(int $photoId): void
    {
        $stmt = $this->db->pdo()->prepare('DELETE FROM product_photos WHERE id = ?');
        $stmt->execute([$photoId]);
    }

    // ------------------------------------------------------------------

    /**
     * Muat varian untuk SEMUA produk pada halaman aktif dalam satu query.
     *
     * @param list<Product> $products
     * @return list<Product>
     */
    private function attachVariants(array $products): array
    {
        if ($products === []) {
            return $products;
        }

        $ids = array_map(static fn(Product $p): int => $p->id, $products);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->db->pdo()->prepare(
            "SELECT * FROM product_variants WHERE product_id IN ({$placeholders}) ORDER BY color, size"
        );
        $stmt->execute($ids);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[(int) $row['product_id']][] = ProductVariant::fromRow($row);
        }

        return array_map(
            static fn(Product $p): Product => $p->withVariants($grouped[$p->id] ?? []),
            $products,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private function bindPayload(array $data): array
    {
        $nullable = static fn(string $key): ?string => isset($data[$key]) && $data[$key] !== '' ? trim((string) $data[$key]) : null;

        return [
            (int) $data['category_id'],
            trim((string) $data['name']),
            $nullable('garment_type'),
            $nullable('description'),
            $nullable('material_spec'),
            $nullable('custom_spec'),
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildFilter(string $search, int $categoryId, string $status): array
    {
        $conditions = [];
        $bind = [];

        if ($status === 'aktif') {
            $conditions[] = 'p.is_active = 1';
        } elseif ($status === 'nonaktif') {
            $conditions[] = 'p.is_active = 0';
        }

        if ($categoryId > 0) {
            $conditions[] = 'p.category_id = ?';
            $bind[] = $categoryId;
        }

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $conditions[] = '(p.name LIKE ? OR p.garment_type LIKE ? OR p.material_spec LIKE ?)';
            $bind = [...$bind, $like, $like, $like];
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $bind];
    }
}
