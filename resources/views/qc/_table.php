<?php

use App\Models\QcInspection;

/**
 * Partial daftar pemeriksaan QC — jawaban HTMX sekaligus render awal.
 *
 * @var App\Core\View $view
 * @var list<QcInspection> $inspections
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $result
 * @var int $productionOrderId
 * @var bool $canRecord
 */
?>
<div class="jf-table-wrap">
    <?php if ($inspections === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pemeriksaan</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $result !== 'semua' || $productionOrderId > 0
                    ? 'Tidak ada pemeriksaan yang cocok dengan filter ini.'
                    : 'Belum ada hasil yang diperiksa QC. Catat pemeriksaan pertama dari perintah produksi yang berjalan.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Waktu</th>
                    <th scope="col">Perintah Produksi</th>
                    <th scope="col" class="jf-text-right">Diperiksa</th>
                    <th scope="col" class="jf-text-right">Lolos / Gagal</th>
                    <th scope="col">Hasil</th>
                    <th scope="col">Inspektur</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inspections as $insp): ?>
                    <tr>
                        <td class="jf-sm jf-text-muted"><?= e(tanggal($insp->inspectedAt)) ?></td>
                        <td>
                            <a class="jf-link" href="<?= e(route('qc.show', ['id' => $insp->id])) ?>"><?= e($insp->productionOrderNumber ?? ('#' . $insp->productionOrderId)) ?></a>
                            <div class="jf-sm jf-text-muted"><?= e($insp->customerName ?? '') ?></div>
                        </td>
                        <td class="jf-text-right"><?= (int) $insp->inspectedQty ?></td>
                        <td class="jf-text-right">
                            <span class="jf-text-success"><?= (int) $insp->passedQty ?></span>
                            /
                            <span class="<?= $insp->failedQty > 0 ? 'jf-text-warning' : 'jf-text-muted' ?>"><?= (int) $insp->failedQty ?></span>
                        </td>
                        <td>
                            <span class="jf-badge <?= e($insp->resultBadge()) ?>"><?= e($insp->resultLabel()) ?></span>
                        </td>
                        <td class="jf-sm jf-text-muted"><?= e($insp->inspectorName ?? '—') ?></td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('qc.show', ['id' => $insp->id])) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('qc.index'),
            'query' => ['q' => $search, 'result' => $result, 'produksi' => $productionOrderId ?: ''],
            'hxTarget' => '#qc-table',
        ]) ?>
    <?php endif; ?>
</div>
