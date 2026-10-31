<?php

/**
 * Daftar perintah produksi: toolbar (cari + filter status + PIC) + tabel.
 *
 * @var App\Core\View $view
 * @var list<App\Models\ProductionOrder> $orders
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var int $pic
 * @var list<array{id:int, name:string, role:string}> $pics
 * @var bool $canManage
 */
$view->extend('layouts.app');

$idx = route('produksi.index');
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e($idx) ?>"
          hx-get="<?= e($idx) ?>" hx-target="#prod-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q" value="<?= e($search) ?>"
                   placeholder="nomor produksi, pesanan, pelanggan"
                   hx-get="<?= e($idx) ?>" hx-target="#prod-table" hx-swap="innerHTML"
                   hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="status">Status</label>
            <select class="jf-input" id="status" name="status" hx-get="<?= e($idx) ?>"
                    hx-target="#prod-table" hx-swap="innerHTML">
                <?php
                $statuses = [
                    'semua' => 'Semua status',
                    'queued' => 'Menunggu Produksi',
                    'running' => 'Berjalan',
                    'completed' => 'Selesai',
                    'cancelled' => 'Dibatalkan',
                ];
                foreach ($statuses as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $status === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="pic">Penanggung Jawab</label>
            <select class="jf-input" id="pic" name="pic" hx-get="<?= e($idx) ?>"
                    hx-target="#prod-table" hx-swap="innerHTML">
                <option value="0">Semua PIC</option>
                <?php foreach ($pics as $person): ?>
                    <option value="<?= (int) $person['id'] ?>" <?= $pic === (int) $person['id'] ? 'selected' : '' ?>>
                        <?= e($person['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>
</div>

<section class="jf-card">
    <div id="prod-table">
        <?= $view->include('production-orders/_table', [
            'orders' => $orders,
            'paginator' => $paginator,
            'search' => $search,
            'status' => $status,
            'pic' => $pic,
        ]) ?>
    </div>
</section>
