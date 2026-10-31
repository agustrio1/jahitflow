<?php

use App\Models\RawMaterial;

/**
 * Daftar bahan baku: toolbar (cari + jenis + status + filter stok menipis) + tabel.
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
$view->extend('layouts.app');

$idx = route('materials.index');
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e($idx) ?>"
          hx-get="<?= e($idx) ?>" hx-target="#materials-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="kode, nama bahan"
                   hx-get="<?= e($idx) ?>" hx-target="#materials-table" hx-swap="innerHTML"
                   hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="type">Jenis</label>
            <select class="jf-input" id="type" name="type" hx-get="<?= e($idx) ?>"
                    hx-target="#materials-table" hx-swap="innerHTML">
                <?php
                $types = [
                    'semua' => 'Semua jenis',
                    RawMaterial::TYPE_UTAMA => 'Bahan Utama',
                    RawMaterial::TYPE_PENOLONG => 'Bahan Penolong',
                    RawMaterial::TYPE_PACKAGING => 'Kemasan',
                ];
                foreach ($types as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $type === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="status">Status</label>
            <select class="jf-input" id="status" name="status" hx-get="<?= e($idx) ?>"
                    hx-target="#materials-table" hx-swap="innerHTML">
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua</option>
            </select>
        </div>
        <div class="jf-field-inline jf-field-inline--check">
            <label class="jf-check">
                <input type="checkbox" name="low" value="1" <?= $lowOnly ? 'checked' : '' ?>
                       hx-get="<?= e($idx) ?>" hx-target="#materials-table" hx-swap="innerHTML">
                Hanya stok menipis
            </label>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>

    <?php if ($canManage): ?>
        <a class="jf-btn jf-btn--primary" href="<?= e(route('materials.create')) ?>">+ Tambah Bahan</a>
    <?php endif; ?>
</div>

<section class="jf-card">
    <div id="materials-table">
        <?= $view->include('materials/_table', [
            'materials' => $materials,
            'paginator' => $paginator,
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'lowOnly' => $lowOnly,
            'canManage' => $canManage,
        ]) ?>
    </div>
</section>
