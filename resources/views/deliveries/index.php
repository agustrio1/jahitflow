<?php

use App\Models\Delivery;

/**
 * Daftar pengiriman: toolbar (cari + status + metode + pesanan) + tabel.
 *
 * @var App\Core\View $view
 * @var list<Delivery> $deliveries
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var string $method
 * @var int $salesOrderId
 * @var list<array{id:int, document_number:string, customer_name:string, status:string, ordered_qty:int, qc_ready:int, shipped:int}> $deliverableOptions
 * @var bool $canCreate
 */
$view->extend('layouts.app');

$idx = route('deliveries.index');
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e($idx) ?>"
          hx-get="<?= e($idx) ?>" hx-target="#delivery-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="no. kirim, pesanan, pelanggan, kurir, resi"
                   hx-get="<?= e($idx) ?>" hx-target="#delivery-table" hx-swap="innerHTML"
                   hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="status">Status</label>
            <select class="jf-input" id="status" name="status" hx-get="<?= e($idx) ?>"
                    hx-target="#delivery-table" hx-swap="innerHTML">
                <?php
                $statuses = ['semua' => 'Semua status'] + array_combine(Delivery::STATUSES, array_map([Delivery::class, 'statusLabelFor'], Delivery::STATUSES));
                foreach ($statuses as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="metode">Metode</label>
            <select class="jf-input" id="metode" name="metode" hx-get="<?= e($idx) ?>"
                    hx-target="#delivery-table" hx-swap="innerHTML">
                <?php
                $methods = ['semua' => 'Semua metode'] + array_combine(Delivery::METHODS, array_map([Delivery::class, 'methodLabelFor'], Delivery::METHODS));
                foreach ($methods as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $method === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="pesanan">Pesanan</label>
            <select class="jf-input" id="pesanan" name="pesanan" hx-get="<?= e($idx) ?>"
                    hx-target="#delivery-table" hx-swap="innerHTML">
                <option value="0">Semua pesanan</option>
                <?php foreach ($deliverableOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>" <?= $salesOrderId === (int) $opt['id'] ? 'selected' : '' ?>>
                        <?= e($opt['document_number']) ?> — <?= e($opt['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>

    <?php if ($canCreate): ?>
        <a class="jf-btn jf-btn--primary" href="<?= e(route('deliveries.create')) ?>">+ Buat Pengiriman</a>
    <?php endif; ?>
</div>

<section class="jf-card">
    <div id="delivery-table">
        <?= $view->include('deliveries/_table', [
            'deliveries' => $deliveries,
            'paginator' => $paginator,
            'search' => $search,
            'status' => $status,
            'method' => $method,
            'salesOrderId' => $salesOrderId,
            'canCreate' => $canCreate,
        ]) ?>
    </div>
</section>
