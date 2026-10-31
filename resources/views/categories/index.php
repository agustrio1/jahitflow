<?php

use App\Models\ProductCategory;

/**
 * Kategori produk: satu form tambah di atas, daftar dengan edit inline
 * (Alpine) dan hapus yang hanya aktif bila kategori tak dipakai produk.
 *
 * @var App\Core\View $view
 * @var list<ProductCategory> $categories
 * @var bool $canManage
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$nameErr = $fieldErrors['name'][0] ?? null;
?>
<?php if ($canManage): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Tambah Kategori</h2>
        <form method="post" action="<?= e(route('categories.store')) ?>" class="jf-form jf-inline-row" novalidate>
            <?= csrf_field() ?>
            <div class="jf-field jf-grow">
                <label class="jf-label" for="name">Nama kategori</label>
                <input class="jf-input<?= $nameErr ? ' is-invalid' : '' ?>" type="text" id="name" name="name"
                       value="<?= e((string) ($oldInput['name'] ?? '')) ?>" maxlength="100" required>
                <?php if ($nameErr): ?><p class="jf-field-error"><?= e($nameErr) ?></p><?php endif; ?>
            </div>
            <div class="jf-field jf-grow">
                <label class="jf-label" for="description">Deskripsi <span class="jf-text-muted jf-sm">(opsional)</span></label>
                <input class="jf-input" type="text" id="description" name="description"
                       value="<?= e((string) ($oldInput['description'] ?? '')) ?>" maxlength="255">
            </div>
            <div class="jf-field">
                <button type="submit" class="jf-btn jf-btn--primary">Simpan</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="jf-card">
    <h2 class="jf-card-title">Daftar Kategori</h2>
    <?php if ($categories === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada kategori</p>
            <p class="jf-empty-text">Tambahkan kategori untuk mengelompokkan produk katalog.</p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Deskripsi</th>
                    <th scope="col">Produk</th>
                    <?php if ($canManage): ?><th scope="col" class="jf-text-right">Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $cat): ?>
                    <tr x-data="{ editing: false }">
                        <td>
                            <span x-show="!editing"><?= e($cat->name) ?></span>
                        </td>
                        <td x-show="!editing" class="jf-text-muted"><?= e($cat->description ?? '—') ?></td>
                        <td x-show="!editing"><?= e((string) $cat->productCount) ?></td>
                        <?php if ($canManage): ?>
                            <td x-show="!editing" class="jf-text-right jf-actions">
                                <button type="button" class="jf-btn jf-btn--sm jf-btn--ghost" @click="editing = true">Ubah</button>
                                <?php if ($cat->productCount === 0): ?>
                                    <form method="post" action="<?= e(route('categories.destroy', ['id' => $cat->id])) ?>" class="jf-inline"
                                          onsubmit="return confirm('Hapus kategori <?= e($cat->name) ?>?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="jf-btn jf-btn--sm jf-btn--danger">Hapus</button>
                                    </form>
                                <?php else: ?>
                                    <span class="jf-btn jf-btn--sm jf-btn--disabled" title="Masih dipakai <?= e((string) $cat->productCount) ?> produk">Hapus</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php if ($canManage): ?>
                        <tr x-show="editing" x-cloak class="jf-edit-row">
                            <td colspan="4">
                                <form method="post" action="<?= e(route('categories.update', ['id' => $cat->id])) ?>" class="jf-form jf-inline-row">
                                    <?= csrf_field() ?>
                                    <div class="jf-field jf-grow">
                                        <label class="jf-label">Nama</label>
                                        <input class="jf-input" type="text" name="name" value="<?= e($cat->name) ?>" maxlength="100" required>
                                    </div>
                                    <div class="jf-field jf-grow">
                                        <label class="jf-label">Deskripsi</label>
                                        <input class="jf-input" type="text" name="description" value="<?= e($cat->description ?? '') ?>" maxlength="255">
                                    </div>
                                    <div class="jf-field jf-actions">
                                        <button type="submit" class="jf-btn jf-btn--sm jf-btn--primary">Simpan</button>
                                        <button type="button" class="jf-btn jf-btn--sm jf-btn--ghost" @click="editing = false">Batal</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
