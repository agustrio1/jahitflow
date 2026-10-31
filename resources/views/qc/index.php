<?php

use App\Models\QcInspection;

/**
 * Daftar pemeriksaan QC: toolbar (cari + hasil + perintah produksi) + tabel.
 *
 * @var App\Core\View $view
 * @var list<QcInspection> $inspections
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $result
 * @var int $productionOrderId
 * @var list<array{id:int, document_number:string, customer_name:string, target_quantity:int}> $producibleOptions
 * @var bool $canRecord
 */
$view->extend('layouts.app');

$idx = route('qc.index');
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e($idx) ?>"
          hx-get="<?= e($idx) ?>" hx-target="#qc-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="no. produksi, pesanan, pelanggan"
                   hx-get="<?= e($idx) ?>" hx-target="#qc-table" hx-swap="innerHTML"
                   hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="result">Hasil</label>
            <select class="jf-input" id="result" name="result" hx-get="<?= e($idx) ?>"
                    hx-target="#qc-table" hx-swap="innerHTML">
                <?php
                $results = [
                    'semua' => 'Semua hasil',
                    QcInspection::RESULT_LOLOS => 'Lolos',
                    QcInspection::RESULT_PERBAIKAN => 'Perlu Perbaikan',
                    QcInspection::RESULT_GAGAL => 'Gagal',
                ];
                foreach ($results as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $result === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="produksi">Perintah Produksi</label>
            <select class="jf-input" id="produksi" name="produksi" hx-get="<?= e($idx) ?>"
                    hx-target="#qc-table" hx-swap="innerHTML">
                <option value="0">Semua perintah</option>
                <?php foreach ($producibleOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>" <?= $productionOrderId === (int) $opt['id'] ? 'selected' : '' ?>>
                        <?= e($opt['document_number']) ?> — <?= e($opt['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>

    <?php if ($canRecord): ?>
        <a class="jf-btn jf-btn--primary" href="<?= e(route('qc.create')) ?>">+ Catat Pemeriksaan</a>
    <?php endif; ?>
</div>

<section class="jf-card">
    <div id="qc-table">
        <?= $view->include('qc/_table', [
            'inspections' => $inspections,
            'paginator' => $paginator,
            'search' => $search,
            'result' => $result,
            'productionOrderId' => $productionOrderId,
            'canRecord' => $canRecord,
        ]) ?>
    </div>
</section>
