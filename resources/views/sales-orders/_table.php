<?php

use App\Models\SalesOrder;

/**
 * Partial daftar pesanan — jawaban HTMX sekaligus render awal.
 *
 * @var App\Core\View $view
 * @var list<SalesOrder> $orders
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var bool $canManage
 */
?>
<div class="jf-table-wrap">
    <?php if ($orders === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pesanan</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $status !== 'semua'
                    ? 'Tidak ada pesanan yang cocok dengan filter ini.'
                    : 'Buat pesanan pertama: pilih pelanggan, tambahkan item, total dihitung otomatis.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Nomor</th>
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Tenggat</th>
                    <th scope="col" class="jf-text-right">Total</th>
                    <th scope="col" class="jf-text-right">DP</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <a class="jf-link" href="<?= e(route('sales_orders.show', ['id' => $order->id])) ?>"><?= e($order->documentNumber) ?></a>
                            <div class="jf-text-muted jf-sm"><?= e(tanggal($order->orderDate)) ?></div>
                        </td>
                        <td><?= e($order->customerName ?? '—') ?></td>
                        <td><?= e(tanggal($order->dueDate)) ?></td>
                        <td class="jf-text-right"><?= e(money($order->grandTotal)) ?></td>
                        <td class="jf-text-right jf-sm"><?= e(number_format($order->dpPercent(), 0, ',', '.') . '%') ?></td>
                        <td><span class="jf-badge <?= e($order->statusBadge()) ?>"><?= e($order->statusLabel()) ?></span></td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('sales_orders.show', ['id' => $order->id])) ?>">Detail</a>
                            <?php if ($canManage && $order->isEditable()): ?>
                                <a class="jf-btn jf-btn--sm" href="<?= e(route('sales_orders.edit', ['id' => $order->id])) ?>">Ubah</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('sales_orders.index'),
            'query' => ['q' => $search, 'status' => $status],
            'hxTarget' => '#orders-table',
        ]) ?>
    <?php endif; ?>
</div>
