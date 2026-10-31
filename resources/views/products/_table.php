<?php

use App\Models\Product;

/**
 * Partial daftar produk — jawaban HTMX sekaligus render awal.
 *
 * @var App\Core\View $view
 * @var list<Product> $products
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var int $categoryId
 * @var string $status
 * @var bool $canManage
 * @var bool $canSetStatus
 */
?>
<div class="jf-table-wrap">
    <?php if ($products === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada produk</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $categoryId > 0 || $status !== 'aktif'
                    ? 'Tidak ada produk yang cocok dengan filter ini.'
                    : 'Tambahkan produk katalog beserta varian warna/ukuran dan harganya.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Produk</th>
                    <th scope="col">Kategori</th>
                    <th scope="col">Varian</th>
                    <th scope="col">Harga satuan</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php $activeVariants = array_filter($product->variants, static fn($v) => $v->isActive); ?>
                    <tr>
                        <td>
                            <a class="jf-link" href="<?= e(route('products.show', ['id' => $product->id])) ?>"><?= e($product->name) ?></a>
                            <?php if ($product->garmentType): ?>
                                <div class="jf-text-muted jf-sm"><?= e($product->garmentType) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($product->categoryName) ?></td>
                        <td><?= e((string) count($activeVariants)) ?> varian</td>
                        <td><?= e($product->priceRange()) ?></td>
                        <td>
                            <?php if ($product->isActive): ?>
                                <span class="jf-badge jf-badge--success">Aktif</span>
                            <?php else: ?>
                                <span class="jf-badge jf-badge--warning">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('products.show', ['id' => $product->id])) ?>">Detail</a>
                            <?php if ($canManage): ?>
                                <a class="jf-btn jf-btn--sm" href="<?= e(route('products.edit', ['id' => $product->id])) ?>">Ubah</a>
                            <?php endif; ?>
                            <?php if ($canSetStatus): ?>
                                <form method="post" action="<?= e(route('products.setStatus', ['id' => $product->id, 'status' => $product->isActive ? 'nonaktif' : 'aktif'])) ?>"
                                      class="jf-inline"
                                      <?= $product->isActive ? 'onsubmit="return confirm(\'Nonaktifkan produk ini?\')"' : '' ?>>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="jf-btn jf-btn--sm <?= $product->isActive ? 'jf-btn--danger' : 'jf-btn--primary' ?>">
                                        <?= $product->isActive ? 'Nonaktif' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('products.index'),
            'query' => ['q' => $search, 'category' => $categoryId, 'status' => $status],
            'hxTarget' => '#products-table',
        ]) ?>
    <?php endif; ?>
</div>
