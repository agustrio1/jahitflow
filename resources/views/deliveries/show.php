<?php

use App\Models\Delivery;

/**
 * Detail satu batch pengiriman + saldo siap-kirim pesanan terkait + tombol
 * transisi status. Kelayakan transisi dijaga DeliveryFlow di server; tombol yang
 * tak muncul hanya petunjuk UI, bukan satu-satunya pagar (§9/§12).
 *
 * @var App\Core\View $view
 * @var Delivery $delivery
 * @var array{ordered: int, qc_ready: int, shipped: int, available: int} $summary
 * @var list<string> $nextStates
 * @var bool $canManage
 */
$view->extend('layouts.app');

$target = min((int) $summary['ordered'], (int) $summary['qc_ready']);
$pct = $target > 0 ? min(100, (int) round(((int) $summary['shipped'] / $target) * 100)) : 0;

$actionLabel = static fn(string $to): string => match ($to) {
    Delivery::STATUS_DIKIRIM => 'Tandai Dikirim',
    Delivery::STATUS_DITERIMA => 'Tandai Diterima',
    Delivery::STATUS_BATAL => 'Batalkan Pengiriman',
    default => Delivery::statusLabelFor($to),
};
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= e($delivery->statusBadge()) ?>"><?= e($delivery->statusLabel()) ?></span>
            <span class="jf-mono"><?= e($delivery->documentNumber) ?></span>
        </div>
        <div class="jf-actions">
            <?php if ($delivery->salesOrderId > 0): ?>
                <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('sales_orders.show', ['id' => $delivery->salesOrderId])) ?>">Lihat Pesanan</a>
            <?php endif; ?>
        </div>
    </div>

    <dl class="jf-dl">
        <div class="jf-dl-row"><dt>Pesanan</dt><dd><?= e($delivery->salesOrderNumber ?? ('#' . $delivery->salesOrderId)) ?></dd></div>
        <?php if ($delivery->customerName): ?>
            <div class="jf-dl-row"><dt>Pelanggan</dt><dd><?= e($delivery->customerName) ?></dd></div>
        <?php endif; ?>
        <div class="jf-dl-row"><dt>Jumlah</dt><dd class="jf-mono"><?= e((string) $delivery->quantity) ?> pcs</dd></div>
        <div class="jf-dl-row"><dt>Metode</dt><dd><?= e($delivery->methodLabel()) ?></dd></div>
        <div class="jf-dl-row"><dt>Kurir / Ekspedisi</dt><dd><?= e($delivery->courier !== null && $delivery->courier !== '' ? $delivery->courier : '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Nomor Resi</dt><dd class="jf-mono"><?= e($delivery->trackingNumber !== null && $delivery->trackingNumber !== '' ? $delivery->trackingNumber : '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Penerima</dt><dd><?= e($delivery->recipient !== null && $delivery->recipient !== '' ? $delivery->recipient : '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Bukti Serah Terima</dt><dd><?= e($delivery->handoverRef !== null && $delivery->handoverRef !== '' ? $delivery->handoverRef : '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Tanggal Pengiriman</dt><dd><?= e(tanggal($delivery->deliveryDate)) ?></dd></div>
        <div class="jf-dl-row"><dt>Dicatat oleh</dt><dd><?= e($delivery->createdByName ?? '—') ?></dd></div>
    </dl>

    <?php if ($canManage && $nextStates !== []): ?>
        <div class="jf-form-actions">
            <?php foreach ($nextStates as $ns): ?>
                <?php $isBatal = $ns === Delivery::STATUS_BATAL; ?>
                <form method="post" action="<?= e(route('deliveries.status', ['id' => $delivery->id])) ?>" class="jf-inline"
                      <?= $isBatal ? 'onsubmit="return confirm(\'Batalkan batch pengiriman ini? Jatah siap-kirim akan dibebaskan.\')"' : '' ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="to" value="<?= e($ns) ?>">
                    <button type="submit" class="jf-btn jf-btn--sm <?= $isBatal ? 'jf-btn--danger' : 'jf-btn--primary' ?>">
                        <?= e($actionLabel($ns)) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php elseif ($delivery->isFinal()): ?>
        <p class="jf-text-muted jf-sm">Status ini sudah akhir — tidak ada transisi lanjutan.</p>
    <?php endif; ?>
</section>

<section class="jf-card">
    <h2 class="jf-card-title">Status Pengiriman Pesanan</h2>
    <div class="jf-progress jf-progress--lg" role="progressbar" aria-valuenow="<?= e((string) $pct) ?>" aria-valuemin="0" aria-valuemax="100">
        <span class="jf-progress__bar" style="width: <?= e((string) $pct) ?>%"></span>
    </div>
    <p class="jf-text-muted jf-sm">
        Terkirim <strong><?= (int) $summary['shipped'] ?></strong> pcs dari siap-kirim <?= (int) $target ?> pcs
        (pesanan <?= (int) $summary['ordered'] ?>, lolos QC <?= (int) $summary['qc_ready'] ?>).
        Sisa dapat dikirim: <strong><?= (int) $summary['available'] ?></strong> pcs.
    </p>
</section>

<?php if ($delivery->note): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Catatan</h2>
        <p><?= nl2br(e($delivery->note)) ?></p>
    </section>
<?php endif; ?>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('deliveries.index')) ?>">← Kembali ke daftar Pengiriman</a>
</div>
