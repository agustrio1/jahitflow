<?php

use App\Models\SalesOrder;

/**
 * Detail pesanan: item, ringkasan nilai, gerbang DP, dan aksi transisi
 * status. Tombol hanya muncul bila policy mengizinkan; eksekusi tetap
 * dijaga service + policy di server (§12 DOC).
 *
 * @var App\Core\View $view
 * @var SalesOrder $order
 * @var bool $canManage
 * @var array<string, list<string>> $fieldErrors
 */
$view->extend('layouts.app');

$generalErr = $fieldErrors['status'][0] ?? $fieldErrors['items'][0] ?? null;
$dpNeeded = round($order->grandTotal * SalesOrder::DP_MIN_RATIO, 2);
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= e($order->statusBadge()) ?>"><?= e($order->statusLabel()) ?></span>
            <?php if ($order->status !== SalesOrder::STATUS_CANCELLED && $order->meetsDpGate()): ?>
                <span class="jf-badge jf-badge--success">DP siap produksi</span>
            <?php endif; ?>
        </div>
        <div class="jf-actions">
            <?php if ($canManage && $order->isEditable()): ?>
                <a class="jf-btn jf-btn--sm" href="<?= e(route('sales_orders.edit', ['id' => $order->id])) ?>">Ubah</a>
            <?php endif; ?>
            <?php if ($canManage && $order->status === SalesOrder::STATUS_DRAFT): ?>
                <form method="post" action="<?= e(route('sales_orders.offer', ['id' => $order->id])) ?>" class="jf-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm">Tandai Penawaran</button>
                </form>
            <?php endif; ?>
            <?php if ($canManage && $order->isEditable()): ?>
                <form method="post" action="<?= e(route('sales_orders.confirm', ['id' => $order->id])) ?>" class="jf-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm jf-btn--primary">Konfirmasi Pesanan</button>
                </form>
                <form method="post" action="<?= e(route('sales_orders.cancel', ['id' => $order->id])) ?>" class="jf-inline"
                      onsubmit="return confirm('Batalkan pesanan ini?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm jf-btn--danger">Batalkan</button>
                </form>
            <?php endif; ?>
            <?php if ($canManage && $order->status === SalesOrder::STATUS_DRAFT): ?>
                <form method="post" action="<?= e(route('sales_orders.destroy', ['id' => $order->id])) ?>" class="jf-inline"
                      onsubmit="return confirm('Hapus draft pesanan ini? Tindakan tidak dapat dibatalkan.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm jf-btn--danger">Hapus</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($generalErr): ?><p class="jf-field-error"><?= e($generalErr) ?></p><?php endif; ?>

    <dl class="jf-dl">
        <div class="jf-dl-row">
            <dt>Pelanggan</dt>
            <dd>
                <a class="jf-link" href="<?= e(route('customers.show', ['id' => $order->customerId])) ?>"><?= e($order->customerName ?? ('#' . $order->customerId)) ?></a>
            </dd>
        </div>
        <div class="jf-dl-row"><dt>Tanggal pesanan</dt><dd><?= e(tanggal($order->orderDate)) ?></dd></div>
        <div class="jf-dl-row"><dt>Tenggat</dt><dd><?= e(tanggal($order->dueDate)) ?></dd></div>
        <div class="jf-dl-row"><dt>Total item</dt><dd><?= e((string) count($order->items)) ?> varian · <?= e((string) $order->totalQuantity()) ?> pcs</dd></div>
    </dl>
</section>

<!-- Item -->
<section class="jf-card">
    <h2 class="jf-card-title">Item Pesanan</h2>
    <?php if ($order->items === []): ?>
        <p class="jf-empty-text">Belum ada item. Ubah pesanan untuk menambahkan item.</p>
    <?php else: ?>
        <div class="jf-table-wrap">
            <table class="jf-table">
                <thead>
                    <tr>
                        <th scope="col">Produk</th>
                        <th scope="col">Varian</th>
                        <th scope="col" class="jf-text-right">Jumlah</th>
                        <th scope="col" class="jf-text-right">Harga satuan</th>
                        <th scope="col" class="jf-text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->items as $item): ?>
                        <tr>
                            <td><?= e($item->productName) ?></td>
                            <td class="jf-text-muted"><?= e($item->variantLabel()) ?></td>
                            <td class="jf-text-right"><?= e((string) $item->quantity) ?></td>
                            <td class="jf-text-right"><?= e(money($item->unitPrice)) ?></td>
                            <td class="jf-text-right"><?= e(money($item->lineTotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Ringkasan nilai -->
    <dl class="jf-dl jf-so-totals">
        <div class="jf-dl-row"><dt>Subtotal</dt><dd class="jf-text-right"><?= e(money($order->subtotal)) ?></dd></div>
        <?php if ($order->discountAmount > 0): ?>
            <div class="jf-dl-row"><dt>Diskon</dt><dd class="jf-text-right">−&nbsp;<?= e(money($order->discountAmount)) ?></dd></div>
        <?php endif; ?>
        <?php if ($order->additionalCost > 0): ?>
            <div class="jf-dl-row"><dt>Biaya tambahan</dt><dd class="jf-text-right">+&nbsp;<?= e(money($order->additionalCost)) ?></dd></div>
        <?php endif; ?>
        <div class="jf-dl-row jf-so-grand"><dt>Total</dt><dd class="jf-text-right"><?= e(money($order->grandTotal)) ?></dd></div>
        <div class="jf-dl-row"><dt>Uang muka (<?= e(number_format($order->dpPercent(), 0, ',', '.')) ?>%)</dt><dd class="jf-text-right"><?= e(money($order->dpAmount)) ?></dd></div>
        <div class="jf-dl-row"><dt>Sisa pembayaran</dt><dd class="jf-text-right"><?= e(money($order->remaining())) ?></dd></div>
    </dl>

    <?php if ($order->status !== SalesOrder::STATUS_CANCELLED): ?>
        <p class="jf-note <?= $order->meetsDpGate() ? 'jf-text-success' : 'jf-text-warning' ?>">
            <?php if ($order->meetsDpGate()): ?>
                Gerbang produksi terpenuhi — DP sudah ≥ 30% dari total.
            <?php else: ?>
                Produksi belum dapat dibuka: DP minimal <?= e(money($dpNeeded)) ?> (30% dari total).
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($canManage && $order->status === SalesOrder::STATUS_CONFIRMED && $order->meetsDpGate()): ?>
        <div class="jf-form-actions">
            <a class="jf-btn jf-btn--primary" href="<?= e(route('produksi.create')) ?>?pesanan=<?= (int) $order->id ?>">Buat Perintah Produksi</a>
        </div>
    <?php endif; ?>

    <?php if ($order->designNotes): ?>
        <div class="jf-fieldset-plain">
            <h3 class="jf-dl-caption">Catatan Desain</h3>
            <p class="jf-note"><?= nl2br(e($order->designNotes)) ?></p>
        </div>
    <?php endif; ?>
</section>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('sales_orders.index')) ?>">← Kembali ke daftar pesanan</a>
    <a class="jf-btn jf-btn--sm jf-btn--icon jf-push" href="<?= e(route('sales_orders.unduh.pdf', ['id' => $order->id])) ?>" target="_blank" rel="noopener">
        <?= icon('printer') ?>Cetak / PDF
    </a>
    <a class="jf-btn jf-btn--sm jf-btn--icon" href="<?= e(route('sales_orders.unduh.csv', ['id' => $order->id])) ?>" rel="nofollow" download>
        <?= icon('download') ?>Unduh CSV
    </a>
</div>
