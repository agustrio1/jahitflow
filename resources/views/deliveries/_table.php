<?php

use App\Models\Delivery;

/**
 * Partial daftar pengiriman — jawaban HTMX sekaligus render awal.
 *
 * @var App\Core\View $view
 * @var list<Delivery> $deliveries
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var string $method
 * @var int $salesOrderId
 * @var bool $canCreate
 */
?>
<div class="jf-table-wrap">
    <?php if ($deliveries === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pengiriman</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $status !== 'semua' || $method !== 'semua' || $salesOrderId > 0
                    ? 'Tidak ada pengiriman yang cocok dengan filter ini.'
                    : 'Belum ada batch pengiriman. Buat pengiriman untuk pesanan yang barangnya sudah lolos QC.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">No. Kirim</th>
                    <th scope="col">Pesanan</th>
                    <th scope="col" class="jf-text-right">Jumlah</th>
                    <th scope="col">Metode / Kurir</th>
                    <th scope="col">Status</th>
                    <th scope="col">Dicatat oleh</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deliveries as $d): ?>
                    <tr>
                        <td class="jf-sm jf-text-muted"><?= e(tanggal($d->deliveryDate)) ?></td>
                        <td><a class="jf-link jf-mono" href="<?= e(route('deliveries.show', ['id' => $d->id])) ?>"><?= e($d->documentNumber) ?></a></td>
                        <td>
                            <?= e($d->salesOrderNumber ?? ('#' . $d->salesOrderId)) ?>
                            <div class="jf-sm jf-text-muted"><?= e($d->customerName ?? '') ?></div>
                        </td>
                        <td class="jf-text-right jf-mono"><?= e((string) $d->quantity) ?> pcs</td>
                        <td class="jf-sm">
                            <?= e($d->methodLabel()) ?>
                            <?php if ($d->courier): ?><div class="jf-text-muted"><?= e($d->courier) ?></div><?php endif; ?>
                        </td>
                        <td><span class="jf-badge <?= e($d->statusBadge()) ?>"><?= e($d->statusLabel()) ?></span></td>
                        <td class="jf-sm jf-text-muted"><?= e($d->createdByName ?? '—') ?></td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('deliveries.show', ['id' => $d->id])) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('deliveries.index'),
            'query' => [
                'q' => $search,
                'status' => $status,
                'metode' => $method,
                'pesanan' => $salesOrderId ?: '',
            ],
            'hxTarget' => '#delivery-table',
        ]) ?>
    <?php endif; ?>
</div>
