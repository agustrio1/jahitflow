<?php

use App\Models\Payment;

/**
 * Partial daftar pembayaran — jawaban HTMX sekaligus render awal.
 *
 * @var App\Core\View $view
 * @var list<Payment> $payments
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $method
 * @var string $phase
 * @var int $salesOrderId
 * @var bool $canRecord
 */
?>
<div class="jf-table-wrap">
    <?php if ($payments === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pembayaran</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $method !== 'semua' || $phase !== 'semua' || $salesOrderId > 0
                    ? 'Tidak ada pembayaran yang cocok dengan filter ini.'
                    : 'Belum ada uang masuk tercatat. Catat pembayaran (uang muka/cicilan/pelunasan) dari pesanan yang aktif.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col">No. Pembayaran</th>
                    <th scope="col">Pesanan</th>
                    <th scope="col">Jenis</th>
                    <th scope="col" class="jf-text-right">Jumlah</th>
                    <th scope="col">Metode</th>
                    <th scope="col">Dicatat oleh</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td class="jf-sm jf-text-muted"><?= e(tanggal($pay->paymentDate)) ?></td>
                        <td><a class="jf-link jf-mono" href="<?= e(route('payments.show', ['id' => $pay->id])) ?>"><?= e($pay->documentNumber) ?></a></td>
                        <td>
                            <?= e($pay->salesOrderNumber ?? ('#' . $pay->salesOrderId)) ?>
                            <div class="jf-sm jf-text-muted"><?= e($pay->customerName ?? '') ?></div>
                        </td>
                        <td><span class="jf-badge <?= e($pay->phaseBadge()) ?>"><?= e($pay->phaseLabel()) ?></span></td>
                        <td class="jf-text-right jf-mono"><?= e(money($pay->amount)) ?></td>
                        <td class="jf-sm"><?= e($pay->methodLabel()) ?></td>
                        <td class="jf-sm jf-text-muted"><?= e($pay->createdByName ?? '—') ?></td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('payments.show', ['id' => $pay->id])) ?>">Detail</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('payments.index'),
            'query' => [
                'q' => $search,
                'metode' => $method,
                'jenis' => $phase,
                'pesanan' => $salesOrderId ?: '',
            ],
            'hxTarget' => '#payment-table',
        ]) ?>
    <?php endif; ?>
</div>
