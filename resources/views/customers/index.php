<?php
/**
 * Halaman daftar pelanggan: toolbar filter (HTMX) + tabel.
 *
 * @var App\Core\View $view
 * @var string $pageTitle
 * @var list<App\Models\Customer> $customers
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var bool $canSetStatus
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$tableData = [
    'customers' => $customers,
    'paginator' => $paginator,
    'search' => $search,
    'status' => $status,
    'canSetStatus' => $canSetStatus,
];
?>
<div class="jf-toolbar">
    <form class="jf-filters" method="get" action="<?= e(route('customers.index')) ?>"
          hx-get="<?= e(route('customers.index')) ?>" hx-target="#customers-table" hx-swap="innerHTML">
        <div class="jf-field-inline">
            <label class="jf-label" for="q">Cari</label>
            <input class="jf-input jf-input--search" type="search" id="q" name="q"
                   value="<?= e($search) ?>" placeholder="nama, telepon, kota"
                   hx-get="<?= e(route('customers.index')) ?>" hx-target="#customers-table"
                   hx-swap="innerHTML" hx-trigger="keyup changed delay:400ms, search">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="status">Status</label>
            <select class="jf-input" id="status" name="status" hx-get="<?= e(route('customers.index')) ?>"
                    hx-target="#customers-table" hx-swap="innerHTML">
                <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua</option>
            </select>
        </div>
        <noscript><button type="submit" class="jf-btn jf-btn--sm">Terapkan</button></noscript>
    </form>

    <a class="jf-btn jf-btn--primary" href="<?= e(route('customers.create')) ?>">+ Tambah Pelanggan</a>
</div>

<section class="jf-card">
    <div id="customers-table">
        <?= $view->include('customers/_table', $tableData) ?>
    </div>
</section>
