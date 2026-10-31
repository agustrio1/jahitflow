<?php

use App\Models\RawMaterial;

/**
 * Form tambah/edit bahan (satu template dua mode). current_stock TIDAK ada di
 * sini — saldo hanya berubah lewat ledger pergerakan (show), bukan form master.
 *
 * @var App\Core\View $view
 * @var RawMaterial|null $material
 * @var string $formAction
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$isEdit = $material !== null;
$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
$val = static function (string $key, mixed $fallback = '') use ($oldInput): mixed {
    return array_key_exists($key, $oldInput) ? $oldInput[$key] : $fallback;
};
$currentType = $val('type', $material?->type ?? RawMaterial::TYPE_UTAMA);
?>
<form method="post" action="<?= e($formAction) ?>" class="jf-card jf-form jf-form-stack" novalidate>
    <?= csrf_field() ?>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="code">Kode Bahan <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('code') ? ' is-invalid' : '' ?>" type="text" id="code" name="code"
                   value="<?= e(strtoupper((string) $val('code', $material?->code))) ?>" maxlength="30" required>
            <p class="jf-hint">Unik, mis. DRL-NVY-01. Huruf/angka/tanda hubung.</p>
            <?php if ($err('code')): ?><p class="jf-field-error"><?= e($err('code')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="name">Nama Bahan <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('name') ? ' is-invalid' : '' ?>" type="text" id="name" name="name"
                   value="<?= e((string) $val('name', $material?->name)) ?>" maxlength="150" required>
            <?php if ($err('name')): ?><p class="jf-field-error"><?= e($err('name')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="type">Jenis <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('type') ? ' is-invalid' : '' ?>" id="type" name="type" required>
                <option value="<?= RawMaterial::TYPE_UTAMA ?>" <?= $currentType === RawMaterial::TYPE_UTAMA ? 'selected' : '' ?>>Bahan Utama</option>
                <option value="<?= RawMaterial::TYPE_PENOLONG ?>" <?= $currentType === RawMaterial::TYPE_PENOLONG ? 'selected' : '' ?>>Bahan Penolong</option>
                <option value="<?= RawMaterial::TYPE_PACKAGING ?>" <?= $currentType === RawMaterial::TYPE_PACKAGING ? 'selected' : '' ?>>Kemasan</option>
            </select>
            <?php if ($err('type')): ?><p class="jf-field-error"><?= e($err('type')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="unit">Satuan <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('unit') ? ' is-invalid' : '' ?>" type="text" id="unit" name="unit"
                   value="<?= e((string) $val('unit', $material?->unit)) ?>" maxlength="20" required placeholder="meter, kg, pcs, lembar">
            <?php if ($err('unit')): ?><p class="jf-field-error"><?= e($err('unit')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="min_stock">Stok Minimum</label>
            <input class="jf-input<?= $err('min_stock') ? ' is-invalid' : '' ?>" type="text" inputmode="decimal" id="min_stock" name="min_stock"
                   value="<?= e((string) $val('min_stock', $material?->minStock ?? '0')) ?>">
            <p class="jf-hint">Ambang peringatan stok menipis (satuan ikut di atas).</p>
            <?php if ($err('min_stock')): ?><p class="jf-field-error"><?= e($err('min_stock')) ?></p><?php endif; ?>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <p class="jf-hint">
            Saldo saat ini: <strong><?= e($material->stockWithUnit()) ?></strong>.
            Saldo tidak diubah di sini — gunakan pencatatan pergerakan stok di halaman detail.
        </p>
    <?php endif; ?>

    <div class="jf-field">
        <label class="jf-label" for="notes">Catatan</label>
        <textarea class="jf-input jf-textarea" id="notes" name="notes" rows="3"
                  maxlength="2000"><?= e((string) $val('notes', $material?->notes)) ?></textarea>
        <?php if ($err('notes')): ?><p class="jf-field-error"><?= e($err('notes')) ?></p><?php endif; ?>
    </div>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Bahan' ?></button>
        <a class="jf-btn jf-btn--ghost" href="<?= $isEdit ? e(route('materials.show', ['id' => $material->id])) : e(route('materials.index')) ?>">Batal</a>
    </div>
</form>
