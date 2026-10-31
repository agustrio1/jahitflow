<?php

use App\Models\Payment;

/**
 * Daftar pembayaran: toolbar (cari + metode + jenis + pesanan) + tabel.
 *
 * @var App\Core\View $view
 * @var list<Payment> $payments
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $method
 * @var string $phase
 * @var int $salesOrderId
 * @var list<array{id:int, document_number:string, customer_name:string, status:string, grand_total:string, paid_total:string}> $payableOptions
 * @var bool $canRecord
 */
$view->extend('layouts.app');

$idx = route('payments.index');
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e($idx) ?>"
          hx-get="<?= e($idx) ?>" hx-target="#payment-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="no. bayar, pesanan, pelanggan"
                   hx-get="<?= e($idx) ?>" hx-target="#payment-table" hx-swap="innerHTML"
                   hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="jenis">Jenis</label>
            <select class="jf-input" id="jenis" name="jenis" hx-get="<?= e($idx) ?>"
                    hx-target="#payment-table" hx-swap="innerHTML">
                <?php
                $phases = ['semua' => 'Semua jenis'] + array_combine(Payment::PHASES, array_map([Payment::class, 'phaseLabelFor'], Payment::PHASES));
                foreach ($phases as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $phase === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="metode">Metode</label>
            <select class="jf-input" id="metode" name="metode" hx-get="<?= e($idx) ?>"
                    hx-target="#payment-table" hx-swap="innerHTML">
                <?php
                $methods = ['semua' => 'Semua metode'] + array_combine(Payment::METHODS, array_map([Payment::class, 'methodLabelFor'], Payment::METHODS));
                foreach ($methods as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $method === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="pesanan">Pesanan</label>
            <select class="jf-input" id="pesanan" name="pesanan" hx-get="<?= e($idx) ?>"
                    hx-target="#payment-table" hx-swap="innerHTML">
                <option value="0">Semua pesanan</option>
                <?php foreach ($payableOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>" <?= $salesOrderId === (int) $opt['id'] ? 'selected' : '' ?>>
                        <?= e($opt['document_number']) ?> — <?= e($opt['customer_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>

    <?php if ($canRecord): ?>
        <a class="jf-btn jf-btn--primary" href="<?= e(route('payments.create')) ?>">+ Catat Pembayaran</a>
    <?php endif; ?>
</div>

<section class="jf-card">
    <div id="payment-table">
        <?= $view->include('payments/_table', [
            'payments' => $payments,
            'paginator' => $paginator,
            'search' => $search,
            'method' => $method,
            'phase' => $phase,
            'salesOrderId' => $salesOrderId,
            'canRecord' => $canRecord,
        ]) ?>
    </div>
</section>
