<?php

use App\Models\Payment;
use App\Support\PaymentCalculator;

/**
 * Form catat pembayaran (hanya tambah — transaksi bersifat log).
 *
 * @var App\Core\View $view
 * @var Payment|null $payment
 * @var list<array{id:int, document_number:string, customer_name:string, status:string, grand_total:string, paid_total:string}> $payableOptions
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
$currentPhase = (string) $val('phase', Payment::PHASE_CICILAN);
$currentMethod = (string) $val('method', Payment::METHOD_TRANSFER);
$hasOptions = $payableOptions !== [];
?>
<?php if (!$hasOptions): ?>
    <div class="jf-card">
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pesanan yang dapat dibayar</p>
            <p class="jf-empty-text">
                Pembayaran menempel pada pesanan yang belum dibatalkan. Buat pesanan lebih dulu.
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
                <?php foreach ($payableOptions as $opt):
                    $sisa = PaymentCalculator::remaining($opt['grand_total'], $opt['paid_total']); ?>
                    <option value="<?= (int) $opt['id'] ?>" <?= $currentOrder === (int) $opt['id'] ? 'selected' : '' ?>>
                        <?= e($opt['document_number']) ?> — <?= e($opt['customer_name']) ?> (sisa <?= e(money($sisa)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('sales_order_id')): ?><p class="jf-field-error"><?= e($err('sales_order_id')) ?></p><?php endif; ?>
            <p class="jf-hint">Sisa tagihan dihitung ulang di server saat menyimpan — angka di sini hanya panduan.</p>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="phase">Jenis Pembayaran <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('phase') ? ' is-invalid' : '' ?>" id="phase" name="phase" required>
                <?php foreach (Payment::PHASES as $code): ?>
                    <option value="<?= e($code) ?>" <?= $currentPhase === $code ? 'selected' : '' ?>><?= e(Payment::phaseLabelFor($code)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('phase')): ?><p class="jf-field-error"><?= e($err('phase')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="amount">Jumlah (Rp) <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('amount') ? ' is-invalid' : '' ?>" type="number" min="0" step="0.01" id="amount" name="amount"
                   value="<?= e((string) $val('amount', '')) ?>" required>
            <?php if ($err('amount')): ?><p class="jf-field-error"><?= e($err('amount')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="method">Metode <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('method') ? ' is-invalid' : '' ?>" id="method" name="method" required>
                <?php foreach (Payment::METHODS as $code): ?>
                    <option value="<?= e($code) ?>" <?= $currentMethod === $code ? 'selected' : '' ?>><?= e(Payment::methodLabelFor($code)) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('method')): ?><p class="jf-field-error"><?= e($err('method')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="payment_date">Tanggal Pembayaran <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('payment_date') ? ' is-invalid' : '' ?>" type="date" id="payment_date" name="payment_date"
                   value="<?= e((string) $val('payment_date', date('Y-m-d'))) ?>" required>
            <?php if ($err('payment_date')): ?><p class="jf-field-error"><?= e($err('payment_date')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="reference">Nomor Referensi</label>
            <input class="jf-input<?= $err('reference') ? ' is-invalid' : '' ?>" type="text" id="reference" name="reference" maxlength="100"
                   value="<?= e((string) $val('reference', '')) ?>" placeholder="no. bukti transfer / struk">
            <?php if ($err('reference')): ?><p class="jf-field-error"><?= e($err('reference')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="note">Catatan</label>
        <textarea class="jf-input jf-textarea<?= $err('note') ? ' is-invalid' : '' ?>" id="note" name="note" rows="2"
                  maxlength="255" placeholder="Catatan transaksi (opsional)."><?= e((string) $val('note', '')) ?></textarea>
        <?php if ($err('note')): ?><p class="jf-field-error"><?= e($err('note')) ?></p><?php endif; ?>
    </div>
    <p class="jf-hint">Pencatatan manual — tidak ada gerbang pembayaran otomatis. Jumlah <strong>tidak boleh melebihi sisa tagihan</strong>.</p>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary">Simpan Pembayaran</button>
        <a class="jf-btn jf-btn--ghost" href="<?= e(route('payments.index')) ?>">Batal</a>
    </div>
</form>
<?php endif; ?>
