<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Repositories\AuditRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Validators\ProductValidator;
use App\Validators\VariantValidator;

/**
 * Logika bisnis katalog produk + varian (§8.C DOC: produk katalog
 * BUKAN stok bahan). Varian membawa unit_price — satu-satunya sumber
 * harga; duplikat kombinasi (product, color, size) ditolak sebelum
 * menyentuh UNIQUE index agar pesan error manusiawi.
 */
final class ProductService
{
    public function __construct(
        private readonly Database $db,
        private readonly ProductRepository $products,
        private readonly ProductCategoryRepository $categories,
        private readonly AuditRepository $audit,
        private readonly ProductValidator $validator,
        private readonly VariantValidator $variantValidator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        $errors = $this->validator->validate($data);
        $this->checkCategory($data, $errors);

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $id = $this->db->transaction(function () use ($data, $userId): int {
            $newId = $this->products->create($data);
            $this->audit->log($userId, 'product.created', 'product', $newId, 'Produk dibuat: ' . trim((string) $data['name']));

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
        $product = $this->findOrFail($id);

        $errors = $this->validator->validate($data);
        $this->checkCategory($data, $errors);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->db->transaction(function () use ($id, $data, $userId, $product): void {
            $this->products->update($id, $data);
            $this->audit->log(
                $userId,
                'product.updated',
                'product',
                $id,
                'Produk diperbarui: ' . $product->name . ' → ' . trim((string) $data['name']),
            );
        });

        return ['ok' => true, 'errors' => []];
    }

    public function setStatus(int $id, bool $active, ?int $userId): void
    {
        $product = $this->findOrFail($id);

        $this->db->transaction(function () use ($id, $active, $userId, $product): void {
            $this->products->setActive($id, $active);
            $this->audit->log(
                $userId,
                $active ? 'product.activated' : 'product.deactivated',
                'product',
                $id,
                ($active ? 'Produk diaktifkan: ' : 'Produk dinonaktifkan: ') . $product->name,
            );
        });
    }

    // ------------------------------------------------------------------
    // Varian
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function addVariant(int $productId, array $data, ?int $userId): array
    {
        $product = $this->findOrFail($productId);

        $errors = $this->variantValidator->validate($data);
        $color = trim((string) ($data['color'] ?? ''));
        $size = trim((string) ($data['size'] ?? ''));
        if ($errors === [] && $color !== '' && $size !== '' && $this->products->variantComboExists($productId, $color, $size)) {
            $errors['color'][] = 'Kombinasi warna dan ukuran ini sudah ada untuk produk tersebut.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $variantId = $this->db->transaction(function () use ($productId, $data, $userId, $product, $color, $size): int {
            $newId = $this->products->addVariant($productId, $data);
            $this->audit->log(
                $userId,
                'variant.created',
                'product_variant',
                $newId,
                'Varian ditambahkan pada ' . $product->name . ': ' . $color . ' / ' . $size,
            );

            return $newId;
        });

        return ['ok' => true, 'id' => $variantId, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function updateVariantPrice(int $variantId, array $data, ?int $userId): array
    {
        $variant = $this->findVariantOrFail($variantId);

        // Hanya harga yang boleh diubah lewat jalur ini; key lain diabaikan.
        $errors = $this->variantValidator->validate([
            'color' => $variant->color,
            'size' => $variant->size,
            'unit_price' => $data['unit_price'] ?? null,
            'sku' => $variant->sku ?? '',
        ]);

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $newPrice = (float) $data['unit_price'];

        $this->db->transaction(function () use ($variantId, $newPrice, $userId, $variant): void {
            $this->products->updateVariantPrice($variantId, $newPrice);
            $this->audit->log(
                $userId,
                'variant.price_updated',
                'product_variant',
                $variantId,
                'Harga varian ' . $variant->color . '/' . $variant->size . ' diubah: ' . money((float) $variant->unitPrice) . ' → ' . money($newPrice),
                ['old_price' => (string) $variant->unitPrice, 'new_price' => (string) $newPrice],
            );
        });

        return ['ok' => true, 'errors' => []];
    }

    public function setVariantActive(int $variantId, bool $active, ?int $userId): void
    {
        $variant = $this->findVariantOrFail($variantId);

        $this->db->transaction(function () use ($variantId, $active, $userId, $variant): void {
            $this->products->setVariantActive($variantId, $active);
            $this->audit->log(
                $userId,
                $active ? 'variant.activated' : 'variant.deactivated',
                'product_variant',
                $variantId,
                ($active ? 'Varian diaktifkan: ' : 'Varian dinonaktifkan: ') . $variant->color . ' / ' . $variant->size,
            );
        });
    }

    public function findOrFail(int $id): Product
    {
        $product = $this->products->find($id);
        if ($product === null) {
            throw new HttpException(404, 'Produk tidak ditemukan.');
        }

        return $product;
    }

    public function findVariantOrFail(int $id): ProductVariant
    {
        $variant = $this->products->findVariant($id);
        if ($variant === null) {
            throw new HttpException(404, 'Varian produk tidak ditemukan.');
        }

        return $variant;
    }

    /**
     * Kategori harus benar-benar ada di database — input id liar ditolak
     * sebelum INSERT memicu error FK (§5 DOC: jangan percaya input browser).
     *
     * @param array<string, mixed> $data
     * @param array<string, list<string>> $errors di-isi lewat referensi
     */
    private function checkCategory(array $data, array &$errors): void
    {
        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId > 0 && $this->categories->find($categoryId) === null) {
            $errors['category_id'][] = 'Kategori terpilih tidak ditemukan, silakan pilih ulang.';
        }
    }
}
