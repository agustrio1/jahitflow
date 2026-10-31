<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\ProductCategory;
use PDO;

/**
 * Akses kategori produk. product_count dihitung sekali lewat subquery —
 * bukan query per baris (hindari N+1, §7 DOC).
 */
final class ProductCategoryRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return list<ProductCategory> */
    public function allWithCount(): array
    {
        $stmt = $this->db->pdo()->query(
            'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM product_categories c
             ORDER BY c.name ASC'
        );

        return array_map(static fn(array $r): ProductCategory => ProductCategory::fromRow($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array<int, ProductCategory> id => kategori, untuk pengisian select */
    public function allById(): array
    {
        $map = [];
        foreach ($this->allWithCount() as $category) {
            $map[$category->id] = $category;
        }

        return $map;
    }

    public function find(int $id): ?ProductCategory
    {
        $stmt = $this->db->pdo()->prepare('SELECT * FROM product_categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : ProductCategory::fromRow($row);
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM product_categories WHERE name = ?';
        $bind = [$name];
        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $bind[] = $exceptId;
        }

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($bind);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(string $name, ?string $description): int
    {
        $stmt = $this->db->pdo()->prepare('INSERT INTO product_categories (name, description) VALUES (?, ?)');
        $stmt->execute([$name, $description]);

        return (int) $this->db->pdo()->lastInsertId();
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $stmt = $this->db->pdo()->prepare('UPDATE product_categories SET name = ?, description = ? WHERE id = ?');
        $stmt->execute([$name, $description, $id]);
    }

    /** Hard delete hanya bila TIDAK ada produk yang memakainya (integritas FK). */
    public function deleteIfUnused(int $id): bool
    {
        $check = $this->db->pdo()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $check->execute([$id]);
        if ((int) $check->fetchColumn() > 0) {
            return false;
        }

        $stmt = $this->db->pdo()->prepare('DELETE FROM product_categories WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
