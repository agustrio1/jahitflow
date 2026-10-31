<?php

use App\Models\SalesOrder;

/**
 * Surat Sales Order untuk cetak / simpan-PDF (keputusan user: HTML surat,
 * tanpa library). Dokumen mandiri (tidak memakai layout app) agar hasil
 * cetakan bersih. Data $order sudah memuat item (product/variant snapshot).
 *
 * @var App\Core\View $view
 * @var SalesOrder $order
 */

// Letterhead usaha (placeholder proyek dummy — bukan data pelanggan).
$perusahaan = 'JAHITFLOW';
$jenisUsaha = 'Konveksi & Pakaian Jadi';
$alamat = "Jl. Industri Tekstil No. 27\r\nBandung, Jawa Barat 40123";
$kontak = "Telp. (022) 1234-5678 · hallo@jahitflow.test";

$totalQty = $order->totalQuantity();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sales Order <?= e($order->documentNumber) ?></title>
    <?= $view->include('partials.print-style') ?>
</head>
<body>
    <div class="doc-toolbar">
        <a class="tb-btn" href="<?= e(route('sales_orders.show', ['id' => $order->id])) ?>">← Kembali</a>
        <span class="doc-hint">Gunakan tombol di bawah atau <b>Ctrl/Cmd + P</b> lalu pilih <b>Simpan sebagai PDF</b>.</span>
        <span class="tb-spacer"></span>
        <button type="button" class="tb-btn tb-btn--primary" onclick="window.print()">
            <?= icon('printer') ?>Cetak / Simpan PDF
        </button>
    </div>

    <main class="sheet">
        <header class="doc-kop">
            <div class="doc-kop__brand">
                <h1 class="doc-kop__name"><?= e($perusahaan) ?></h1>
                <p class="doc-kop__tag"><?= e($jenisUsaha) ?></p>
                <p class="doc-kop__addr"><?= nl2br(e($alamat)) ?><br><?= e($kontak) ?></p>
            </div>
            <div class="doc-meta">
                <p class="doc-meta__title">Sales Order</p>
                <p class="doc-meta__no"><?= e($order->documentNumber) ?></p>
                <div class="doc-meta__lines">
                    <span>Tanggal: <b><?= e(tanggal($order->orderDate)) ?></b></span>
                    <span>Tenggat: <b><?= e(tanggal($order->dueDate)) ?></b></span>
                    <span>Status: <b><?= e($order->statusLabel()) ?></b></span>
                </div>
            </div>
        </header>

        <div class="doc-rule"></div>

        <section class="doc-parties">
            <div class="doc-party">
                <p class="doc-party__label">Kepada Yth.</p>
                <p class="doc-party__name"><?= e($order->customerName ?? ('Pelanggan #' . $order->customerId)) ?></p>
                <p class="doc-party__sub">Pesanan kerja konveksi</p>
            </div>
            <div class="doc-party" style="text-align:right">
                <p class="doc-party__label">Jumlah</p>
                <p class="doc-party__name"><?= (int) $totalQty ?> pcs</p>
                <p class="doc-party__sub"><?= count($order->items) ?> varian item</p>
            </div>
        </section>

        <p class="doc-section-title">Rincian Item</p>
        <?php if ($order->items === []): ?>
            <p class="doc-empty">Belum ada item pada dokumen ini.</p>
        <?php else: ?>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th class="t-center" style="width:32px">No</th>
                        <th>Produk</th>
                        <th>Varian</th>
                        <th class="t-right">Qty</th>
                        <th class="t-right">Harga Satuan</th>
                        <th class="t-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $n = 0; foreach ($order->items as $item): $n++; ?>
                        <tr>
                            <td class="t-center"><?= $n ?></td>
                            <td><?= e($item->productName) ?></td>
                            <td class="t-muted"><?= e($item->variantLabel()) ?></td>
                            <td class="t-right"><?= (int) $item->quantity ?></td>
                            <td class="t-right"><?= e(money($item->unitPrice)) ?></td>
                            <td class="t-right"><?= e(money($item->lineTotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="doc-totals">
            <table>
                <tr><td class="lbl">Subtotal</td><td class="val"><?= e(money($order->subtotal)) ?></td></tr>
                <?php if ($order->discountAmount > 0): ?>
                    <tr><td class="lbl">Diskon</td><td class="val">−&nbsp;<?= e(money($order->discountAmount)) ?></td></tr>
                <?php endif; ?>
                <?php if ($order->additionalCost > 0): ?>
                    <tr><td class="lbl">Biaya tambahan</td><td class="val">+&nbsp;<?= e(money($order->additionalCost)) ?></td></tr>
                <?php endif; ?>
                <tr class="grand"><td class="lbl">Total</td><td class="val"><?= e(money($order->grandTotal)) ?></td></tr>
                <tr><td class="lbl">Uang muka (<?= e(number_format($order->dpPercent(), 0, ',', '.')) ?>%)</td><td class="val"><?= e(money($order->dpAmount)) ?></td></tr>
                <tr><td class="lbl">Sisa pembayaran</td><td class="val"><?= e(money($order->remaining())) ?></td></tr>
            </table>
        </div>

        <?php if ($order->designNotes): ?>
            <div class="doc-notes">
                <h4>Catatan Desain</h4>
                <p><?= e($order->designNotes) ?></p>
            </div>
        <?php endif; ?>

        <section class="doc-sign">
            <div class="doc-sign__box">
                <p class="doc-sign__role">Penerima</p>
                <div class="doc-sign__line"></div>
                <p class="doc-sign__name">( <?= e($order->customerName ?? '................................') ?> )</p>
            </div>
            <div class="doc-sign__box">
                <p class="doc-sign__role">Hormat kami, <?= e($perusahaan) ?></p>
                <div class="doc-sign__line"></div>
                <p class="doc-sign__name">( ................................ )</p>
            </div>
        </section>

        <p class="doc-foot">
            Dokumen dihasilkan oleh sistem JahitFlow · Diekspor pada <?= e(date('d/m/Y H:i')) ?> ·
            Nilai dalam Rupiah.
        </p>
    </main>
</body>
</html>
