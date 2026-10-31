<?php

use App\Models\ProductionOrder;

/**
 * Partial daftar perintah produksi — jawaban HTMX sekaligus render awal.
 *
 * @var App\Core\View $view
 * @var list<ProductionOrder> $orders
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var int $pic
 */
?>
<div class="jf-table-wrap">
    <?php if ($orders === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada perintah produksi</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $status !== 'semua' || $pic > 0
                    ? 'Tidak ada perintah produksi yang cocok dengan filter ini.'
                    : 'Perintah produksi dibuat dari pesanan yang sudah dikonfirmasi dan DP-nya memenuhi 30%.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Nomor</th>
                    <th scope="col">Pesanan</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Target</th>
                    <th scope="col" style="min-width: 9rem;">Progres</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a class="jf-link" href="<?= e(route('produksi.show', ['id' => $order->id])) ?>"><?= e($order->documentNumber) ?></a>
                            <div class="jf-text-muted jf-sm">Target <?= e((string) $order->targetQuantity) ?> pcs</div>
                        </td>
                        <td>
                            <a class="jf-link" href="<?= e(route('sales_orders.show', ['id' => $order->salesOrderId])) ?>"><?= e($order->salesOrderNumber ?? ('#' . $order->salesOrderId)) ?></a>
                        </td>
                        <td><?= e($order->customerName ?? '—') ?></td>
                        <td class="<?= $order->isOverdue() ? 'jf-text-warning' : '' ?>"><?= e(tanggal($order->targetDate)) ?></td>
                        <td>
                            <?php $pct = $order->progressPercent(); ?>
                            <div class="jf-progress" role="progressbar" aria-valuenow="<?= e((string) $pct) ?>" aria-valuemin="0" aria-valuemax="100">
                                <span class="jf-progress__bar" style="width: <?= e((string) $pct) ?>%"></span>
                            </div>
                            <div class="jf-text-muted jf-sm"><?= e((string) $pct) ?>%</div>
                        </td>
                        <td><span class="jf-badge <?= e($order->statusBadge()) ?>"><?= e($order->statusLabel()) ?></span></td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('produksi.show', ['id' => $order->id])) ?>">Papan Tahap</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('produksi.index'),
            'query' => ['q' => $search, 'status' => $status, 'pic' => $pic],
            'hxTarget' => '#prod-table',
        ]) ?>
    <?php endif; ?>
</div>
