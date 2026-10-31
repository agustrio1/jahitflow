<?php

use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Detail produk: spesifikasi, varian (harga dikelola per varian —
 * sumber tunggal harga pesanan), dan foto referensi. Semua aksi tulis
 * dijaga ProductPolicy di controller; tombol hanya tampil bila boleh.
 *
 * @var App\Core\View $view
 * @var Product $product
 * @var bool $canManage
 * @var bool $canSetStatus
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$verr = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
$variantHasError = ($verr('color') || $verr('size') || $verr('unit_price') || $verr('sku')) ? 'true' : 'false';
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= $product->isActive ? 'jf-badge--success' : 'jf-badge--warning' ?>">
                <?= $product->isActive ? 'Aktif' : 'Nonaktif' ?>
            </span>
            <span class="jf-badge jf-badge--info"><?= e($product->categoryName) ?></span>
        </div>
        <div class="jf-actions">
            <?php if ($canManage): ?>
                <a class="jf-btn jf-btn--sm" href="<?= e(route('products.edit', ['id' => $product->id])) ?>">Ubah Produk</a>
            <?php endif; ?>
            <?php if ($canSetStatus): ?>
                <form method="post" action="<?= e(route('products.setStatus', ['id' => $product->id, 'status' => $product->isActive ? 'nonaktif' : 'aktif'])) ?>"
                      class="jf-inline"
                      <?= $product->isActive ? 'onsubmit="return confirm(\'Nonaktifkan produk ini?\')"' : '' ?>>
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm <?= $product->isActive ? 'jf-btn--danger' : 'jf-btn--primary' ?>">
                        <?= $product->isActive ? 'Nonaktifkan' : 'Aktifkan kembali' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <dl class="jf-dl">
        <div class="jf-dl-row"><dt>Jenis</dt><dd><?= e($product->garmentType ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Bahan</dt><dd><?= e($product->materialSpec ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Rentang harga</dt><dd><?= e($product->priceRange()) ?></dd></div>
    </dl>

    <?php if ($product->description): ?>
        <p class="jf-note"><?= nl2br(e($product->description)) ?></p>
    <?php endif; ?>

    <?php if ($product->customSpec): ?>
        <div class="jf-fieldset-plain">
            <h3 class="jf-dl-caption">Spesifikasi Custom</h3>
            <p class="jf-note"><?= nl2br(e($product->customSpec)) ?></p>
        </div>
    <?php endif; ?>
</section>

<!-- Varian -->
<section class="jf-card" x-data="{ addOpen: <?= $variantHasError ?> }">
    <div class="jf-detail-head">
        <h2 class="jf-card-title jf-mb-0">Varian Warna &amp; Ukuran</h2>
        <?php if ($canManage): ?>
            <button type="button" class="jf-btn jf-btn--sm jf-btn--primary" @click="addOpen = !addOpen" x-text="addOpen ? 'Tutup' : '+ Tambah Varian'"></button>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <form method="post" action="<?= e(route('products.addVariant', ['id' => $product->id])) ?>"
              class="jf-form jf-inline-row jf-mt-1" x-show="addOpen" x-cloak novalidate>
            <?= csrf_field() ?>
            <div class="jf-field jf-grow">
                <label class="jf-label" for="color">Warna <span class="jf-req">*</span></label>
                <input class="jf-input<?= $verr('color') ? ' is-invalid' : '' ?>" type="text" id="color" name="color"
                       value="<?= e((string) ($oldInput['color'] ?? '')) ?>" maxlength="60" placeholder="Hitam">
                <?php if ($verr('color')): ?><p class="jf-field-error"><?= e($verr('color')) ?></p><?php endif; ?>
            </div>
            <div class="jf-field">
                <label class="jf-label" for="size">Ukuran <span class="jf-req">*</span></label>
                <input class="jf-input<?= $verr('size') ? ' is-invalid' : '' ?>" type="text" id="size" name="size"
                       value="<?= e((string) ($oldInput['size'] ?? '')) ?>" maxlength="20" placeholder="M" style="max-width:110px">
                <?php if ($verr('size')): ?><p class="jf-field-error"><?= e($verr('size')) ?></p><?php endif; ?>
            </div>
            <div class="jf-field">
                <label class="jf-label" for="unit_price">Harga (Rp) <span class="jf-req">*</span></label>
                <input class="jf-input<?= $verr('unit_price') ? ' is-invalid' : '' ?>" type="number" id="unit_price" name="unit_price"
                       value="<?= e((string) ($oldInput['unit_price'] ?? '')) ?>" min="0" step="1" style="max-width:150px">
                <?php if ($verr('unit_price')): ?><p class="jf-field-error"><?= e($verr('unit_price')) ?></p><?php endif; ?>
            </div>
            <div class="jf-field">
                <label class="jf-label" for="sku">SKU <span class="jf-text-muted jf-sm">(opsional)</span></label>
                <input class="jf-input<?= $verr('sku') ? ' is-invalid' : '' ?>" type="text" id="sku" name="sku"
                       value="<?= e((string) ($oldInput['sku'] ?? '')) ?>" maxlength="60" style="max-width:150px">
                <?php if ($verr('sku')): ?><p class="jf-field-error"><?= e($verr('sku')) ?></p><?php endif; ?>
            </div>
            <div class="jf-field">
                <button type="submit" class="jf-btn jf-btn--primary">Simpan Varian</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($product->variants === []): ?>
        <p class="jf-empty-text jf-mt-1">Belum ada varian. Tambahkan varian warna/ukuran beserta harga satuannya.</p>
    <?php else: ?>
        <table class="jf-table jf-mt-1">
            <thead>
                <tr>
                    <th scope="col">Warna</th>
                    <th scope="col">Ukuran</th>
                    <th scope="col">SKU</th>
                    <th scope="col">Harga satuan</th>
                    <th scope="col">Status</th>
                    <?php if ($canManage): ?><th scope="col" class="jf-text-right">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($product->variants as $variant): ?>
                    <?php /** @var ProductVariant $variant */ ?>
                    <tr>
                        <td><?= e($variant->color) ?></td>
                        <td><?= e($variant->size) ?></td>
                        <td class="jf-text-muted"><?= e($variant->sku ?? '—') ?></td>
                        <?php if ($canManage): ?>
                            <td>
                                <form method="post" action="<?= e(route('products.variantPrice', ['variantId' => $variant->id])) ?>" class="jf-inline jf-price-form">
                                    <?= csrf_field() ?>
                                    <input class="jf-input jf-input--price" type="number" name="unit_price" min="0" step="1"
                                           value="<?= e((string) $variant->unitPrice) ?>" aria-label="Harga <?= e($variant->label()) ?>">
                                    <button type="submit" class="jf-btn jf-btn--sm jf-btn--ghost">Simpan</button>
                                </form>
                            </td>
                        <?php else: ?>
                            <td><?= e(money($variant->unitPrice)) ?></td>
                        <?php endif; ?>
                        <td>
                            <?php if ($variant->isActive): ?>
                                <span class="jf-badge jf-badge--success">Aktif</span>
                            <?php else: ?>
                                <span class="jf-badge jf-badge--warning">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <?php if ($canManage): ?>
                            <td class="jf-text-right">
                                <form method="post" action="<?= e(route('products.variantStatus', ['variantId' => $variant->id, 'status' => $variant->isActive ? 'nonaktif' : 'aktif'])) ?>" class="jf-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="jf-btn jf-btn--sm jf-btn--ghost"><?= $variant->isActive ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- Foto referensi -->
<section class="jf-card" x-data="{ upOpen: false }">
    <div class="jf-detail-head">
        <h2 class="jf-card-title jf-mb-0">Foto Referensi</h2>
        <?php if ($canManage): ?>
            <button type="button" class="jf-btn jf-btn--sm" @click="upOpen = !upOpen" x-text="upOpen ? 'Tutup' : '+ Unggah Foto'"></button>
        <?php endif; ?>
    </div>

    <?php if ($canManage): ?>
        <?php $photoErr = $fieldErrors['photo'][0] ?? null; ?>
        <form method="post" action="<?= e(route('products.photo.store', ['id' => $product->id])) ?>"
              enctype="multipart/form-data" class="jf-form jf-inline-row jf-mt-1" x-show="upOpen" x-cloak>
            <?= csrf_field() ?>
            <div class="jf-field jf-grow">
                <label class="jf-label" for="photo">Gambar (JPG/PNG/WebP, maks 2&nbsp;MB)</label>
                <input class="jf-input<?= $photoErr ? ' is-invalid' : '' ?>" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required>
                <?php if ($photoErr): ?><p class="jf-field-error"><?= e($photoErr) ?></p><?php endif; ?>
            </div>
            <div class="jf-field">
                <button type="submit" class="jf-btn jf-btn--primary">Unggah</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($product->photos === []): ?>
        <p class="jf-empty-text jf-mt-1">Belum ada foto referensi.</p>
    <?php else: ?>
        <div class="jf-photo-grid jf-mt-1">
            <?php foreach ($product->photos as $photo): ?>
                <figure class="jf-photo">
                    <img src="<?= e(route('products.photo.show', ['photoId' => (int) $photo['id']])) ?>"
                         alt="<?= e($photo['original_name']) ?>" loading="lazy">
                    <figcaption>
                        <span class="jf-photo-name" title="<?= e($photo['original_name']) ?>"><?= e($photo['original_name']) ?></span>
                        <?php if ($canManage): ?>
                            <form method="post" action="<?= e(route('products.photo.destroy', ['photoId' => (int) $photo['id']])) ?>"
                                  class="jf-inline" onsubmit="return confirm('Hapus foto ini?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="jf-btn jf-btn--sm jf-btn--danger">Hapus</button>
                            </form>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('products.index')) ?>">← Kembali ke katalog</a>
</div>
