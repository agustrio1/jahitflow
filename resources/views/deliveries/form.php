<?php

use App\Models\Delivery;

/**
 * Form pembuatan batch pengiriman (§8.I). Jumlah dikunci saat dibuat; status
 * awal "Disiapkan" lalu bertransisi dari halaman detail. Batas siap-kirim
 * (min pesanan & lolos QC − sudah dikirim) hanya panduan — ditegakkan server.
 *
 * @var App\Core\View $view
 * @var Delivery|null $delivery
 * @var list<array{id:int, document_number:string, customer_name:string, status:string, ordered_qty:int, qc_ready:int, shipped:int}> $deliverableOptions
 * @var int $selectedOrderId
 * @var string $formAction
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
$val = static function (string $key, mixed $fallback = '') use ($oldInput): mixed {
    return array_key_exists($key, $oldInput) ? $oldInput[$key] : $fallback;
};
$currentOrder = (int) $val('sales_order_id', $selectedOrderId ?: 0);
$currentMethod = (string) $val('method', Delivery::METHOD_KURIR);
$hasOptions = $deliverableOptions !== [];

// Batas siap-kirim per pesanan: min(pesanan, lolos QC) − sudah dikirim (non-batal).
$availableFor = static fn(array $opt): int => max(0, min((int) $opt['ordered_qty'], (int) $opt['qc_ready']) - (int) $opt['shipped']);
?>
<?php if (!$hasOptions): ?>
    <div class="jf-card">
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pesanan yang dapat dikirim</p>
            <p class="jf-empty-text">
                Pengiriman menempel pada pesanan yang belum dibatalkan. Buat pesanan &amp; selesaikan QC lebih dulu.
            </p>
            <a class="jf-btn jf-btn--ghost" href="<?= e(route('sales_orders.index')) ?>">Lihat Sales Order</a>
        </div>
    </div>
<?php else: ?>
<form method="post" action="<?= e($formAction) ?>" class="jf-card jf-form jf-form-stack" novalidate>
    <?= csrf_field() ?>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="sales_order_id">Pesanan <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('sales_order_id') ? ' is-invalid' : '' ?>" id="sales_order_id" name="sales_order_id" required>
                <option value="0">— Pilih pesanan —</option>
                <?php foreach ($deliverableOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>" <?= $currentOrder === (int) $opt['id'] ? 'selected' : '' ?>>
                        <?= e($opt['document_number']) ?> — <?= e($opt['customer_name']) ?> (siap kirim <?= (int) $availableFor($opt) ?> pcs)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('sales_order_id')): ?><p class="jf-field-error"><?= e($err('sales_order_id')) ?></p><?php endif; ?>
            <p class="jf-hint">"Siap kirim" = min(jumlah pesanan, lolos QC) − sudah dikirim. Angka final ditegakkan server saat menyimpan.</p>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="quantity">Jumlah (pcs) <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('quantity') ? ' is-invalid' : '' ?>" type="number" min="1" step="1" id="quantity" name="quantity"
                   value="<?= e((string) $val('quantity', '')) ?>" required>
            <?php if ($err('quantity')): ?><p class="jf-field-error"><?= e($err('quantity')) ?></p><?php endif; ?>
            <p class="jf-hint">Bulat &gt; 0, tidak melebihi jumlah siap kirim.</p>
        </div>
    </div>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="delivery_date">Tanggal Pengiriman <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('delivery_date') ? ' is-invalid' : '' ?>" type="date" id="delivery_date" name="delivery_date"
                   value="<?= e((string) $val('delivery_date', date('Y-m-d'))) ?>" required>
            <?php if ($err('delivery_date')): ?><p class="jf-field-error"><?= e($err('delivery_date')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="method">Metode <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('method') ? ' is-invalid' : '' ?>" id="method" name="method" required>
                <?php foreach (Delivery::METHODS as $code): ?>
                    <option value="<?= e($code) ?>" <?= $currentMethod === $code ? 'selected' : '' ?>><?= e(Delivery::methodLabelFor($code)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('method')): ?><p class="jf-field-error"><?= e($err('method')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="courier">Kurir / Ekspedisi</label>
            <input class="jf-input<?= $err('courier') ? ' is-invalid' : '' ?>" type="text" id="courier" name="courier" maxlength="120"
                   value="<?= e((string) $val('courier', '')) ?>" placeholder="nama kurir / portal ekspedisi">
            <?php if ($err('courier')): ?><p class="jf-field-error"><?= e($err('courier')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="tracking_number">Nomor Resi</label>
            <input class="jf-input<?= $err('tracking_number') ? ' is-invalid' : '' ?>" type="text" id="tracking_number" name="tracking_number" maxlength="120"
                   value="<?= e((string) $val('tracking_number', '')) ?>" placeholder="nomor pelacakan (opsional)">
            <?php if ($err('tracking_number')): ?><p class="jf-field-error"><?= e($err('tracking_number')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="recipient">Penerima</label>
            <input class="jf-input<?= $err('recipient') ? ' is-invalid' : '' ?>" type="text" id="recipient" name="recipient" maxlength="150"
                   value="<?= e((string) $val('recipient', '')) ?>" placeholder="nama penerima di lokasi">
            <?php if ($err('recipient')): ?><p class="jf-field-error"><?= e($err('recipient')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="handover_ref">Bukti Serah Terima</label>
            <input class="jf-input<?= $err('handover_ref') ? ' is-invalid' : '' ?>" type="text" id="handover_ref" name="handover_ref" maxlength="150"
                   value="<?= e((string) $val('handover_ref', '')) ?>" placeholder="no. DO / tanda terima">
            <?php if ($err('handover_ref')): ?><p class="jf-field-error"><?= e($err('handover_ref')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="note">Catatan</label>
        <textarea class="jf-input jf-textarea<?= $err('note') ? ' is-invalid' : '' ?>" id="note" name="note" rows="2"
                  maxlength="255" placeholder="Catatan pengiriman (opsional)."><?= e((string) $val('note', '')) ?></textarea>
        <?php if ($err('note')): ?><p class="jf-field-error"><?= e($err('note')) ?></p><?php endif; ?>
    </div>
    <p class="jf-hint">Batch dibuat dengan status <strong>Disiapkan</strong>. Transisi ke Dikirim/Diterima/Batal dilakukan dari halaman detail.</p>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary">Simpan Pengiriman</button>
        <a class="jf-btn jf-btn--ghost" href="<?= e(route('deliveries.index')) ?>">Batal</a>
    </div>
</form>
<?php endif; ?>
