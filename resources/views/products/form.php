<?php

use App\Models\Product;
use App\Models\ProductCategory;

/**
 * Form tambah/edit produk katalog. Varian & harga dikelola di halaman
 * detail, bukan di sini (satu tanggung jawab per layar).
 *
 * @var App\Core\View $view
 * @var Product|null $product
 * @var list<ProductCategory> $categories
 * @var string $formAction
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$isEdit = $product !== null;

$val = static function (string $key, mixed $fallback = '') use ($oldInput): mixed {
    return array_key_exists($key, $oldInput) ? $oldInput[$key] : $fallback;
};

$currentCategory = (int) $val('category_id', $product?->categoryId ?? 0);
$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
?>
<form method="post" action="<?= e($formAction) ?>" class="jf-card jf-form jf-form-stack" novalidate>
    <?= csrf_field() ?>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="name">Nama Produk <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('name') ? ' is-invalid' : '' ?>" type="text" id="name" name="name"
                   value="<?= e((string) $val('name', $product?->name)) ?>" maxlength="150" required>
            <?php if ($err('name')): ?><p class="jf-field-error"><?= e($err('name')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="category_id">Kategori <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('category_id') ? ' is-invalid' : '' ?>" id="category_id" name="category_id" required>
                <option value="0">— Pilih kategori —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat->id ?>" <?= $currentCategory === $cat->id ? 'selected' : '' ?>>
                        <?= e($cat->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('category_id')): ?><p class="jf-field-error"><?= e($err('category_id')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="garment_type">Jenis Pakaian</label>
            <input class="jf-input" type="text" id="garment_type" name="garment_type"
                   value="<?= e((string) $val('garment_type', $product?->garmentType)) ?>" maxlength="100"
                   placeholder="mis. Kaos Polo, Kemeja, Tas">
        </div>

        <div class="jf-field">
            <label class="jf-label" for="material_spec">Spesifikasi Bahan</label>
            <input class="jf-input<?= $err('material_spec') ? ' is-invalid' : '' ?>" type="text" id="material_spec" name="material_spec"
                   value="<?= e((string) $val('material_spec', $product?->materialSpec)) ?>" maxlength="255"
                   placeholder="mis. Cotton Combed 30s">
            <?php if ($err('material_spec')): ?><p class="jf-field-error"><?= e($err('material_spec')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="description">Deskripsi</label>
        <textarea class="jf-input jf-textarea" id="description" name="description" rows="2"
                  maxlength="255"><?= e((string) $val('description', $product?->description)) ?></textarea>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="custom_spec">Spesifikasi Custom <span class="jf-text-muted jf-sm">(opsional)</span></label>
        <textarea class="jf-input jf-textarea" id="custom_spec" name="custom_spec" rows="3"
                  maxlength="2000" placeholder="detail konstruksi, sablon, bordir, dll."><?= e((string) $val('custom_spec', $product?->customSpec)) ?></textarea>
        <?php if ($err('custom_spec')): ?><p class="jf-field-error"><?= e($err('custom_spec')) ?></p><?php endif; ?>
    </div>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Produk' ?></button>
        <a class="jf-btn jf-btn--ghost" href="<?= $isEdit ? e(route('products.show', ['id' => $product->id])) : e(route('products.index')) ?>">Batal</a>
    </div>
</form>
