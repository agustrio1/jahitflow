<?php

use App\Models\Payment;
use App\Support\PaymentCalculator;

/**
 * Detail satu transaksi pembayaran (log, baca-saja) + saldo pesanan terkait.
 *
 * @var App\Core\View $view
 * @var Payment $payment
 * @var array{grand_total: string, paid_total: string, remaining: string, status: string, percent: float} $summary
 */
$view->extend('layouts.app');

$statusLabel = PaymentCalculator::statusLabel($summary['status']);
$statusBadge = PaymentCalculator::statusBadge($summary['status']);
$pct = $summary['percent'];
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= e($payment->phaseBadge()) ?>"><?= e($payment->phaseLabel()) ?></span>
            <span class="jf-mono"><?= e($payment->documentNumber) ?></span>
        </div>
        <div class="jf-actions">
            <?php if ($payment->salesOrderId > 0): ?>
                <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('sales_orders.show', ['id' => $payment->salesOrderId])) ?>">Lihat Pesanan</a>
            <?php endif; ?>
        </div>
    </div>

    <dl class="jf-dl">
        <div class="jf-dl-row"><dt>Pesanan</dt><dd><?= e($payment->salesOrderNumber ?? ('#' . $payment->salesOrderId)) ?></dd></div>
        <?php if ($payment->customerName): ?>
            <div class="jf-dl-row"><dt>Pelanggan</dt><dd><?= e($payment->customerName) ?></dd></div>
        <?php endif; ?>
        <div class="jf-dl-row"><dt>Jumlah</dt><dd class="jf-mono jf-text-success"><?= e(money($payment->amount)) ?></dd></div>
        <div class="jf-dl-row"><dt>Metode</dt><dd><?= e($payment->methodLabel()) ?></dd></div>
        <div class="jf-dl-row"><dt>Tanggal</dt><dd><?= e(tanggal($payment->paymentDate)) ?></dd></div>
        <div class="jf-dl-row"><dt>Nomor Referensi</dt><dd><?= e($payment->reference !== null && $payment->reference !== '' ? $payment->reference : '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Dicatat oleh</dt><dd><?= e($payment->createdByName ?? '—') ?></dd></div>
    </dl>
</section>

<section class="jf-card">
    <h2 class="jf-card-title">Status Pembayaran Pesanan</h2>
    <div class="jf-progress jf-progress--lg" role="progressbar" aria-valuenow="<?= e((string) $pct) ?>" aria-valuemin="0" aria-valuemax="100">
        <span class="jf-progress__bar" style="width: <?= e((string) $pct) ?>%"></span>
    </div>
    <p class="jf-text-muted jf-sm">
        <span class="jf-badge <?= e($statusBadge) ?>"><?= e($statusLabel) ?></span>
        — sudah diterima <?= e(money($summary['paid_total'])) ?> dari <?= e(money($summary['grand_total'])) ?>
        (sisa <?= e(money($summary['remaining'])) ?>).
    </p>
</section>

<?php if ($payment->note): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Catatan</h2>
        <p><?= nl2br(e($payment->note)) ?></p>
    </section>
<?php endif; ?>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('payments.index')) ?>">← Kembali ke daftar Pembayaran</a>
</div>
