<?php

use App\Models\User;

/**
 * Dashboard operasional (§8.A). Mengutamakan pekerjaan yang perlu ditindaklanjuti,
 * bukan hanya angka besar. Panel digate per peran (dari controller). Angka jujur —
 * nilai pesanan dipisah dari pendapatan diterima. Tidak ada metrik dekoratif.
 *
 * @var App\Core\View $view
 * @var User|null $user
 * @var array $dashboard
 * @var bool $canSeeFinance
 * @var bool $canSeeOrders
 * @var bool $canSeeProduction
 * @var bool $canSeeDeliveries
 */
$view->extend('layouts.app');

$counts = $dashboard['counts'];
$money = $dashboard['money'];

// Teks sisa-hari menuju tenggat.
$dueText = static function (int $d): string {
    if ($d === 0) {
        return 'Hari ini';
    }
    if ($d > 0) {
        return $d . ' hari lagi';
    }

    return abs($d) . ' hari terlambat';
};

$tiles = [
    ['label' => 'Pesanan baru', 'value' => (int) $counts['orders_new'], 'href' => route('sales_orders.index'), 'icon' => 'cart'],
    ['label' => 'Sedang diproduksi', 'value' => (int) $counts['in_production'], 'href' => route('produksi.index'), 'icon' => 'factory'],
    ['label' => 'Mendekati tenggat', 'value' => (int) $counts['due_soon'], 'href' => route('sales_orders.index'), 'icon' => 'cart'],
    ['label' => 'Terlambat', 'value' => (int) $counts['overdue'], 'href' => route('sales_orders.index'), 'icon' => 'cart', 'alert' => (int) $counts['overdue'] > 0],
    ['label' => 'Menunggu QC', 'value' => (int) $counts['awaiting_qc'], 'href' => route('qc.index'), 'icon' => 'check'],
    ['label' => 'Pengiriman terbuka', 'value' => (int) $counts['open_deliveries'], 'href' => route('deliveries.index'), 'icon' => 'truck'],
];
?>
<section class="jf-card">
    <h2 class="jf-card-title">Selamat bekerja, <?= e($user?->name ?? 'Rekan') ?></h2>
    <p class="jf-text-muted jf-mb-0">
        Anda masuk sebagai
        <span class="jf-badge jf-badge--info"><?= e($user?->roleLabel() ?? '—') ?></span>.
        Berikut pekerjaan yang menunggu tindakan hari ini.
    </p>
</section>

<div class="jf-kpis">
    <?php foreach ($tiles as $tile): ?>
        <a class="jf-kpi<?= !empty($tile['alert']) ? ' jf-kpi--alert' : '' ?>" href="<?= e($tile['href']) ?>">
            <div class="jf-kpi__label"><?= e($tile['label']) ?></div>
            <div class="jf-kpi__value"><?= (int) $tile['value'] ?></div>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($canSeeFinance): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Ringkasan Keuangan</h2>
        <dl class="jf-dl">
            <div class="jf-dl-row">
                <dt>Nilai pesanan dikonfirmasi</dt>
                <dd class="jf-mono"><?= e(money($money['order_value'])) ?></dd>
            </div>
            <div class="jf-dl-row">
                <dt>Pendapatan diterima</dt>
                <dd class="jf-mono jf-text-success"><?= e(money($money['received'])) ?></dd>
            </div>
            <div class="jf-dl-row">
                <dt>Piutang berjalan</dt>
                <dd class="jf-mono jf-text-warning"><?= e(money($money['receivable'])) ?></dd>
            </div>
        </dl>
        <p class="jf-hint">
            "Nilai pesanan" adalah nilai kontrak, bukan uang yang sudah masuk. "Pendapatan diterima" = total pembayaran tercatat.
        </p>
    </section>
<?php endif; ?>

<?php if ($canSeeOrders): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Mendekati Tenggat (7 hari)</h2>
        <?php if ($dashboard['dueSoon'] === []): ?>
            <div class="jf-empty"><p class="jf-empty-text">Tidak ada pesanan yang mendekati tenggat.</p></div>
        <?php else: ?>
            <div class="jf-table-wrap">
                <table class="jf-table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Tenggat</th>
                            <th>Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['dueSoon'] as $o): ?>
                            <tr>
                                <td><a class="jf-link jf-mono" href="<?= e(route('sales_orders.show', ['id' => $o['id']])) ?>"><?= e($o['document_number']) ?></a></td>
                                <td><?= e($o['customer_name']) ?></td>
                                <td><?= e(tanggal((string) $o['due_date'])) ?></td>
                                <td><span class="jf-badge jf-badge--warning"><?= e($dueText((int) $o['due_in_days'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="jf-card">
        <h2 class="jf-card-title">Pekerjaan Terlambat</h2>
        <?php if ($dashboard['overdue'] === []): ?>
            <div class="jf-empty"><p class="jf-empty-text">Tidak ada pesanan yang lewat tenggat. Kerja bagus.</p></div>
        <?php else: ?>
            <div class="jf-table-wrap">
                <table class="jf-table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Pelanggan</th>
                            <th>Tenggat</th>
                            <th>Keterlambatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['overdue'] as $o): ?>
                            <tr>
                                <td><a class="jf-link jf-mono" href="<?= e(route('sales_orders.show', ['id' => $o['id']])) ?>"><?= e($o['document_number']) ?></a></td>
                                <td><?= e($o['customer_name']) ?></td>
                                <td class="jf-text-warning"><?= e(tanggal((string) $o['due_date'])) ?></td>
                                <td><span class="jf-badge jf-badge--danger"><?= e($dueText((int) $o['due_in_days'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($canSeeProduction): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Menunggu Quality Control</h2>
        <?php if ($dashboard['awaitingQc'] === []): ?>
            <div class="jf-empty"><p class="jf-empty-text">Tidak ada perintah produksi yang menunggu pemeriksaan QC.</p></div>
        <?php else: ?>
            <div class="jf-table-wrap">
                <table class="jf-table">
                    <thead>
                        <tr>
                            <th>No. Produksi</th>
                            <th>Pesanan</th>
                            <th class="jf-text-right">Target (pcs)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['awaitingQc'] as $p): ?>
                            <tr>
                                <td><a class="jf-link jf-mono" href="<?= e(route('produksi.show', ['id' => $p['id']])) ?>"><?= e($p['document_number']) ?></a></td>
                                <td class="jf-sm jf-text-muted"><?= e($p['sales_order_number']) ?></td>
                                <td class="jf-text-right jf-mono"><?= (int) $p['target_quantity'] ?></td>
                                <td><span class="jf-badge <?= e($p['status_badge']) ?>"><?= e($p['status_label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($canSeeDeliveries): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Pengiriman Belum Selesai</h2>
        <?php if ($dashboard['openDeliveries'] === []): ?>
            <div class="jf-empty"><p class="jf-empty-text">Tidak ada pengiriman yang belum selesai.</p></div>
        <?php else: ?>
            <div class="jf-table-wrap">
                <table class="jf-table">
                    <thead>
                        <tr>
                            <th>No. Kirim</th>
                            <th>Pesanan</th>
                            <th class="jf-text-right">Jumlah</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard['openDeliveries'] as $d): ?>
                            <tr>
                                <td><a class="jf-link jf-mono" href="<?= e(route('deliveries.show', ['id' => $d['id']])) ?>"><?= e($d['document_number']) ?></a></td>
                                <td class="jf-sm">
                                    <?= e($d['sales_order_number']) ?>
                                    <div class="jf-text-muted"><?= e($d['customer_name']) ?></div>
                                </td>
                                <td class="jf-text-right jf-mono"><?= (int) $d['quantity'] ?> pcs</td>
                                <td class="jf-sm jf-text-muted"><?= e(tanggal((string) $d['delivery_date'])) ?></td>
                                <td><span class="jf-badge <?= e($d['status_badge']) ?>"><?= e($d['status_label']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
