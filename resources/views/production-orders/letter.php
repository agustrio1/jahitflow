<?php

use App\Models\ProductionOrder;

/**
 * Surat Production Order untuk cetak / simpan-PDF (HTML surat, tanpa library).
 * Dokumen mandiri — papan tahap dirender sebagai tabel rincian kerja.
 *
 * @var App\Core\View $view
 * @var ProductionOrder $order
 */

// Letterhead usaha (placeholder proyek dummy — bukan data pelanggan).
$perusahaan = 'JAHITFLOW';
$jenisUsaha = 'Konveksi & Pakaian Jadi';
$alamat = "Jl. Industri Tekstil No. 27\r\nBandung, Jawa Barat 40123";
$kontak = "Telp. (022) 1234-5678 · hallo@jahitflow.test";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Production Order <?= e($order->documentNumber) ?></title>
    <?= $view->include('partials.print-style') ?>
</head>
<body>
    <div class="doc-toolbar">
        <a class="tb-btn" href="<?= e(route('produksi.show', ['id' => $order->id])) ?>">← Kembali</a>
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
                <p class="doc-meta__title">Production Order</p>
                <p class="doc-meta__no"><?= e($order->documentNumber) ?></p>
                <div class="doc-meta__lines">
                    <span>Dibuat: <b><?= e(tanggal($order->createdAt)) ?></b></span>
                    <span>Tanggal target: <b><?= e(tanggal($order->targetDate)) ?></b></span>
                    <span>Status: <b><?= e($order->statusLabel()) ?></b></span>
                </div>
            </div>
        </header>

        <div class="doc-rule"></div>

        <section class="doc-parties">
            <div class="doc-party">
                <p class="doc-party__label">Pelanggan</p>
                <p class="doc-party__name"><?= e($order->customerName ?? '—') ?></p>
                <p class="doc-party__sub">Pesanan sumber: <?= e($order->salesOrderNumber ?? ('#' . $order->salesOrderId)) ?></p>
            </div>
            <div class="doc-party" style="text-align:right">
                <p class="doc-party__label">Penanggung Jawab</p>
                <p class="doc-party__name"><?= e($order->assignedUserName ?? 'Belum ditentukan') ?></p>
                <p class="doc-party__sub">Target: <?= (int) $order->targetQuantity ?> pcs</p>
            </div>
        </section>

        <p class="doc-section-title">Rincian Tahap Produksi</p>
        <?php if ($order->stages === []): ?>
            <p class="doc-empty">Belum ada tahap pada dokumen ini.</p>
        <?php else: ?>
            <table class="doc-table">
                <thead>
                    <tr>
                        <th class="t-center" style="width:32px">No</th>
                        <th>Tahap</th>
                        <th>Status</th>
                        <th class="t-right">Target</th>
                        <th class="t-right">Realisasi</th>
                        <th>PIC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->stages as $stage): ?>
                        <tr>
                            <td class="t-center"><?= (int) $stage->position ?></td>
                            <td><?= e($stage->stageName) ?></td>
                            <td class="t-muted"><?= e($stage->statusLabel()) ?></td>
                            <td class="t-right"><?= (int) $stage->targetQty ?></td>
                            <td class="t-right"><?= (int) $stage->realizedQty ?></td>
                            <td class="t-muted"><?= e($stage->assignedUserName ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($order->description): ?>
            <div class="doc-notes">
                <h4>Catatan Produksi</h4>
                <p><?= e($order->description) ?></p>
            </div>
        <?php endif; ?>

        <section class="doc-sign">
            <div class="doc-sign__box">
                <p class="doc-sign__role">Disusun oleh</p>
                <div class="doc-sign__line"></div>
                <p class="doc-sign__name">( ................................ )</p>
            </div>
            <div class="doc-sign__box">
                <p class="doc-sign__role">Disetujui oleh</p>
                <div class="doc-sign__line"></div>
                <p class="doc-sign__name">( ................................ )</p>
            </div>
            <div class="doc-sign__box">
                <p class="doc-sign__role">Penanggung Jawab</p>
                <div class="doc-sign__line"></div>
                <p class="doc-sign__name">( <?= e($order->assignedUserName ?? '................................') ?> )</p>
            </div>
        </section>

        <p class="doc-foot">
            Dokumen dihasilkan oleh sistem JahitFlow · Diekspor pada <?= e(date('d/m/Y H:i')) ?>.
        </p>
    </main>
</body>
</html>
