<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\ProductCategory;
use App\Repositories\AuditRepository;
use App\Repositories\ProductCategoryRepository;
use App\Validators\CategoryValidator;

/**
 * Logika bisnis kategori produk. Kategori adalah data master kecil:
 * hard delete hanya boleh bila tidak dipakai produk sama sekali
 * (deleteIfUnused menutup celah race dibanding cek di controller).
 */
final class ProductCategoryService
{
    public function __construct(
        private readonly Database $db,
        private readonly ProductCategoryRepository $categories,
        private readonly AuditRepository $audit,
        private readonly CategoryValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        $errors = $this->validator->validate($data);
        $name = trim((string) ($data['name'] ?? ''));

        if ($name !== '' && $this->categories->nameExists($name)) {
            $errors['name'][] = 'Kategori dengan nama ini sudah ada.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $id = $this->db->transaction(function () use ($data, $userId, $name): int {
            $newId = $this->categories->create($name, $this->nullableText($data['description'] ?? null));
            $this->audit->log($userId, 'category.created', 'product_category', $newId, 'Kategori dibuat: ' . $name);

            return $newId;
        });

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function update(int $id, array $data, ?int $userId): array
    {
        $category = $this->findOrFail($id);
        $name = trim((string) ($data['name'] ?? ''));

        $errors = $this->validator->validate($data);
        if ($name !== '' && $this->categories->nameExists($name, $id)) {
            $errors['name'][] = 'Kategori dengan nama ini sudah ada.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->db->transaction(function () use ($id, $name, $data, $userId, $category): void {
            $this->categories->update($id, $name, $this->nullableText($data['description'] ?? null));
            $this->audit->log(
                $userId,
                'category.updated',
                'product_category',
                $id,
                'Kategori diperbarui: ' . $category->name . ' → ' . $name,
            );
        });

        return ['ok' => true, 'errors' => []];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function delete(int $id, ?int $userId): array
    {
        $category = $this->findOrFail($id);

        $result = $this->db->transaction(function () use ($id, $userId, $category): array {
            $deleted = $this->categories->deleteIfUnused($id);
            if ($deleted) {
                $this->audit->log($userId, 'category.deleted', 'product_category', $id, 'Kategori dihapus: ' . $category->name);
            }

            return ['ok' => $deleted];
        });

        if (!$result['ok']) {
            return [
                'ok' => false,
                'message' => 'Kategori masih dipakai produk dan tidak dapat dihapus. Nonaktifkan produk terkait terlebih dahulu.',
            ];
        }

        return ['ok' => true, 'message' => 'Kategori "' . $category->name . '" berhasil dihapus.'];
    }

    public function findOrFail(int $id): ProductCategory
    {
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new HttpException(404, 'Kategori tidak ditemukan.');
        }

        return $category;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? null : $text;
    }
}
