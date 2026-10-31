<?php

/**
 * Hub Laporan (§8.J): kartu tautan ke tiap jenis laporan.
 *
 * @var App\Core\View $view
 * @var list<array{key: string, label: string, desc: string, icon: string}> $reports
 */
$view->extend('layouts.app');
?>
<div class="jf-reports">
    <?php foreach ($reports as $r): ?>
        <a class="jf-report-card" href="<?= e(route('reports.show', ['key' => $r['key']])) ?>">
            <span class="jf-report-card__title"><?= icon($r['icon']) ?> <?= e($r['label']) ?></span>
            <p class="jf-report-card__desc"><?= e($r['desc']) ?></p>
        </a>
    <?php endforeach; ?>
</div>

<p class="jf-text-muted jf-sm">
    Pilih laporan untuk menampilkan angka per rentang tanggal. Setiap laporan dapat diunduh sebagai CSV
    memakai filter periode yang sedang aktif.
</p>
