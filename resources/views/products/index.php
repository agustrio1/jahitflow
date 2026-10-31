<?php

use App\Models\ProductCategory;

/**
 * Halaman katalog produk: toolbar filter (cari, kategori, status) + tabel.
 *
 * @var App\Core\View $view
 * @var list<App\Models\Product> $products
 * @var list<ProductCategory> $categories
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var int $categoryId
 * @var string $status
 * @var bool $canManage
 * @var bool $canSetStatus
 */
$view->extend('layouts.app');

$idx = route('products.index');
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e($idx) ?>"
          hx-get="<?= e($idx) ?>" hx-target="#products-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="nama, tipe, bahan"
                   hx-get="<?= e($idx) ?>" hx-target="#products-table" hx-swap="innerHTML"
                   hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="category">Kategori</label>
            <select class="jf-input" id="category" name="category" hx-get="<?= e($idx) ?>"
                    hx-target="#products-table" hx-swap="innerHTML">
                <option value="0">Semua kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat->id ?>" <?= $categoryId === $cat->id ? 'selected' : '' ?>>
                        <?= e($cat->name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="status">Status</label>
            <select class="jf-input" id="status" name="status" hx-get="<?= e($idx) ?>"
                    hx-target="#products-table" hx-swap="innerHTML">
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua</option>
            </select>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>

    <?php if ($canManage): ?>
        <a class="jf-btn jf-btn--primary" href="<?= e(route('products.create')) ?>">+ Tambah Produk</a>
    <?php endif; ?>
</div>

<section class="jf-card">
    <div id="products-table">
        <?= $view->include('products/_table', [
            'products' => $products,
            'paginator' => $paginator,
            'search' => $search,
            'categoryId' => $categoryId,
            'status' => $status,
            'canManage' => $canManage,
            'canSetStatus' => $canSetStatus,
        ]) ?>
    </div>
</section>
