<?php

declare(strict_types=1);

use App\Core\Seeder;

/**
 * Katalog demo FIKTIF: kategori, produk, varian warna/ukuran + harga.
 * Idempoten (insertIfMissing + lookup id), aman dijalankan berulang.
 *
 * Urutan (Batch H): Demo03 — sesudah pelanggan, sebelum pesanan (item pesanan
 * merujuk SKU varian di sini).
 */
class Demo03ProductCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Seragam Sekolah', 'description' => 'Batik OSIS, putih-abu, olahraga'],
            ['name' => 'Kaus Komunitas', 'description' => 'Kaus event & komunitas'],
            ['name' => 'Merchandise', 'description' => 'Tumbler kain, tote bag, topi'],
            ['name' => 'Seragam Perusahaan', 'description' => 'Kemeja, polo, field shirt'],
            ['name' => 'Pakaian Custom', 'description' => 'Kebaya, jas, seragam khusus'],
        ];

        foreach ($categories as $category) {
            $this->insertIfMissing('product_categories', ['name' => $category['name']], $category);
        }

        foreach ($this->productRows() as [$categoryName, $row, $variants]) {
            $categoryId = $this->lookupId('product_categories', 'name', $categoryName);
            if ($categoryId === 0) {
                continue;
            }

            $row['category_id'] = $categoryId;
            $this->insertIfMissing('products', ['name' => $row['name']], $row);
            $productId = $this->lookupId('products', 'name', $row['name']);

            foreach ($variants as $variant) {
                $variant['product_id'] = $productId;
                $this->insertIfMissing(
                    'product_variants',
                    ['product_id' => $productId, 'color' => $variant['color'], 'size' => $variant['size']],
                    $variant,
                );
            }
        }
    }

    /**
     * Baris produk siap-insert tanpa kategori (kategori dipetakan terpisah)
     * supaya insertIfMissing tidak membawa key non-kolom.
     *
     * @return list<array{0: string, 1: array<string, mixed>, 2: list<array<string, mixed>>}>
     */
    private function productRows(): array
    {
        return [
            ['Seragam Sekolah', [
                'name' => 'Batik OSIS Model Prima',
                'garment_type' => 'Kemeja batik lengan pendek',
                'material_spec' => 'Katun motif cap, gramasi 110 gsm',
                'custom_spec' => 'Sobek nama bordir di dada kiri',
            ], [
                ['color' => 'Navy-Motif', 'size' => 'S', 'unit_price' => 115000, 'sku' => 'BTS-S-NV'],
                ['color' => 'Navy-Motif', 'size' => 'M', 'unit_price' => 120000, 'sku' => 'BTS-M-NV'],
                ['color' => 'Navy-Motif', 'size' => 'L', 'unit_price' => 125000, 'sku' => 'BTS-L-NV'],
            ]],
            ['Kaus Komunitas', [
                'name' => 'Kaus Combed 30s Ringan',
                'garment_type' => 'T-shirt crewneck',
                'material_spec' => 'Cotton combed 30s, jahitan rantai',
                'custom_spec' => 'Sablon rubber 4 warna opsional',
            ], [
                ['color' => 'Putih', 'size' => 'M', 'unit_price' => 75000, 'sku' => 'KC30-M-PTH'],
                ['color' => 'Hitam', 'size' => 'M', 'unit_price' => 75000, 'sku' => 'KC30-M-HTM'],
                ['color' => 'Navy', 'size' => 'L', 'unit_price' => 80000, 'sku' => 'KC30-L-NVY'],
            ]],
            ['Seragam Perusahaan', [
                'name' => 'Field Shirt Kontraktor AD-1',
                'garment_type' => 'Kemeja kerja lengan panjang',
                'material_spec' => 'Drill twill 280 gsm, reflective tape',
                'custom_spec' => 'Logo bordir perusahaan di lengan',
            ], [
                ['color' => 'Biru Dongker', 'size' => 'L', 'unit_price' => 185000, 'sku' => 'FS-L-BDR'],
                ['color' => 'Biru Dongker', 'size' => 'XL', 'unit_price' => 190000, 'sku' => 'FS-XL-BDR'],
            ]],
        ];
    }

    private function lookupId(string $table, string $column, string $value): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1");
        $stmt->execute([$value]);

        return (int) $stmt->fetchColumn();
    }
}
