<?php

/**
 * Daftar sales order: toolbar (cari + filter status) + tabel.
 *
 * @var App\Core\View $view
 * @var list<App\Models\SalesOrder> $orders
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var bool $canManage
 */
$view->extend('layouts.app');

$idx = route('sales_orders.index');
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e($idx) ?>"
          hx-get="<?= e($idx) ?>" hx-target="#orders-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="nomor pesanan, pelanggan"
                   hx-get="<?= e($idx) ?>" hx-target="#orders-table" hx-swap="innerHTML"
                   hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="status">Status</label>
            <select class="jf-input" id="status" name="status" hx-get="<?= e($idx) ?>"
                    hx-target="#orders-table" hx-swap="innerHTML">
                <?php
                $statuses = [
                    'semua' => 'Semua status',
                    'draft' => 'Draft',
                    'offered' => 'Penawaran',
                    'confirmed' => 'Dikonfirmasi',
                    'cancelled' => 'Dibatalkan',
                ];
                foreach ($statuses as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>

    <?php if ($canManage): ?>
        <a class="jf-btn jf-btn--primary" href="<?= e(route('sales_orders.create')) ?>">+ Pesanan Baru</a>
    <?php endif; ?>
</div>

<section class="jf-card">
    <div id="orders-table">
        <?= $view->include('sales-orders/_table', [
            'orders' => $orders,
            'paginator' => $paginator,
            'search' => $search,
            'status' => $status,
            'canManage' => $canManage,
        ]) ?>
    </div>
</section>
