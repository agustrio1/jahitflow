<?php

use App\Models\RawMaterial;

/**
 * Partial daftar bahan — jawaban HTMX sekaligus render awal.
 *
 * @var App\Core\View $view
 * @var list<RawMaterial> $materials
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $type
 * @var string $status
 * @var bool $lowOnly
 * @var bool $canManage
 */
?>
<div class="jf-table-wrap">
    <?php if ($materials === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada bahan</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $type !== 'semua' || $status !== 'aktif' || $lowOnly
                    ? 'Tidak ada bahan yang cocok dengan filter ini.'
                    : 'Daftar bahan baku masih kosong. Tambahkan bahan, lalu catat penerimaannya.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Kode</th>
                    <th scope="col">Nama Bahan</th>
                    <th scope="col">Jenis</th>
                    <th scope="col" class="jf-text-right">Stok</th>
                    <th scope="col" class="jf-text-right">Minimum</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materials as $material): ?>
                    <tr>
                        <td><span class="jf-mono"><?= e($material->code) ?></span></td>
                        <td>
                            <a class="jf-link" href="<?= e(route('materials.show', ['id' => $material->id])) ?>"><?= e($material->name) ?></a>
                        </td>
                        <td class="jf-text-muted jf-sm"><?= e($material->typeLabel()) ?></td>
                        <td class="jf-text-right">
                            <span class="<?= $material->isLowStock() ? 'jf-text-warning' : '' ?>"><?= e($material->stockWithUnit()) ?></span>
                            <?php if ($material->isLowStock()): ?>
                                <div><span class="jf-badge jf-badge--warning">Menipis</span></div>
                            <?php endif; ?>
                        </td>
                        <td class="jf-text-right jf-sm jf-text-muted"><?= e(RawMaterial::formatQty($material->minStock)) ?></td>
                        <td>
                            <span class="jf-badge <?= $material->isActive ? 'jf-badge--success' : 'jf-badge--danger' ?>">
                                <?= $material->isActive ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        </td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('materials.show', ['id' => $material->id])) ?>">Detail</a>
                            <?php if ($canManage): ?>
                                <a class="jf-btn jf-btn--sm" href="<?= e(route('materials.edit', ['id' => $material->id])) ?>">Ubah</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('materials.index'),
            'query' => ['q' => $search, 'type' => $type, 'status' => $status, 'low' => $lowOnly ? 1 : ''],
            'hxTarget' => '#materials-table',
        ]) ?>
    <?php endif; ?>
</div>
