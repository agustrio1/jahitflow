<?php

use App\Models\QcInspection;

/**
 * Detail satu pemeriksaan QC (log, baca-saja).
 *
 * @var App\Core\View $view
 * @var QcInspection $inspection
 */
$view->extend('layouts.app');

$pct = $inspection->passRate();
$undecided = $inspection->undecidedQty();
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= e($inspection->resultBadge()) ?>"><?= e($inspection->resultLabel()) ?></span>
        </div>
        <div class="jf-actions">
            <?php if ($inspection->productionOrderId > 0): ?>
                <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('produksi.show', ['id' => $inspection->productionOrderId])) ?>">Lihat Perintah Produksi</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="jf-progress jf-progress--lg" role="progressbar" aria-valuenow="<?= e((string) $pct) ?>" aria-valuemin="0" aria-valuemax="100">
        <span class="jf-progress__bar" style="width: <?= e((string) $pct) ?>%"></span>
    </div>
    <p class="jf-text-muted jf-sm">Tingkat lolos: <?= e((string) $pct) ?>% dari <?= (int) $inspection->inspectedQty ?> diperiksa</p>

    <dl class="jf-dl">
        <div class="jf-dl-row"><dt>Perintah Produksi</dt><dd><?= e($inspection->productionOrderNumber ?? ('#' . $inspection->productionOrderId)) ?></dd></div>
        <?php if ($inspection->salesOrderNumber): ?>
            <div class="jf-dl-row"><dt>Pesanan</dt><dd><?= e($inspection->salesOrderNumber) ?></dd></div>
        <?php endif; ?>
        <?php if ($inspection->customerName): ?>
            <div class="jf-dl-row"><dt>Pelanggan</dt><dd><?= e($inspection->customerName) ?></dd></div>
        <?php endif; ?>
        <div class="jf-dl-row"><dt>Diperiksa</dt><dd><?= (int) $inspection->inspectedQty ?></dd></div>
        <div class="jf-dl-row"><dt>Lolos</dt><dd class="jf-text-success"><?= (int) $inspection->passedQty ?></dd></div>
        <div class="jf-dl-row"><dt>Gagal</dt><dd class="<?= $inspection->failedQty > 0 ? 'jf-text-warning' : '' ?>"><?= (int) $inspection->failedQty ?></dd></div>
        <?php if ($undecided > 0): ?>
            <div class="jf-dl-row"><dt>Belum diputuskan</dt><dd class="jf-text-muted"><?= $undecided ?></dd></div>
        <?php endif; ?>
        <div class="jf-dl-row"><dt>Inspektur</dt><dd><?= e($inspection->inspectorName ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Waktu</dt><dd><?= e(tanggal($inspection->inspectedAt)) ?></dd></div>
    </dl>
</section>

<?php if ($inspection->defectNote): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Catatan Cacat</h2>
        <p><?= nl2br(e($inspection->defectNote)) ?></p>
    </section>
<?php endif; ?>

<?php if ($inspection->reworkAction): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Tindakan Perbaikan</h2>
        <p><?= nl2br(e($inspection->reworkAction)) ?></p>
    </section>
<?php endif; ?>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('qc.index')) ?>">← Kembali ke daftar QC</a>
</div>
