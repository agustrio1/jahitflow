<?php

use App\Models\Customer;
use App\Models\SalesOrder;

/**
 * Form buat/edit pesanan. Editor item + prakiraan total memakai Alpine
 * HANYA untuk umpan balik visual; nilai final SELALU dihitung ulang di
 * server dari harga varian database (lihat SalesOrderService + OrderTotals).
 *
 * @var App\Core\View $view
 * @var SalesOrder|null $order
 * @var list<Customer> $customers
 * @var list<array{id:int, label:string, unit_price:string, product_name:string}> $variantOptions
 * @var string $formAction
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$isEdit = $order !== null;
$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
$itemErrors = $fieldErrors['items'] ?? [];

$val = static function (string $key, mixed $fallback = '') use ($oldInput): mixed {
    return array_key_exists($key, $oldInput) ? $oldInput[$key] : $fallback;
};

// Baris awal: old input (setelah error) > item pesanan > satu baris kosong.
if (array_key_exists('items', $oldInput) && is_array($oldInput['items'])) {
    $initialRows = array_map(static fn(array $r): array => [
        'variant_id' => (string) ($r['variant_id'] ?? ''),
        'quantity' => (int) ($r['quantity'] ?? 1),
    ], $oldInput['items']);
} elseif ($isEdit && $order->items !== []) {
    $initialRows = [];
    foreach ($order->items as $item) {
        $initialRows[] = ['variant_id' => (string) $item->productVariantId, 'quantity' => $item->quantity];
    }
} else {
    $initialRows = [['variant_id' => '', 'quantity' => 1]];
}
if ($initialRows === []) {
    $initialRows = [['variant_id' => '', 'quantity' => 1]];
}

// Peta harga (id => float) untuk prakiraan client-side.
$prices = [];
foreach ($variantOptions as $opt) {
    $prices[(string) $opt['id']] = (float) $opt['unit_price'];
}

$initData = json_encode([
    'rows' => $initialRows,
    'prices' => $prices,
    'discount' => (float) $val('discount_amount', $order?->discountAmount ?? 0),
    'additional' => (float) $val('additional_cost', $order?->additionalCost ?? 0),
    'dp' => (float) $val('dp_amount', $order?->dpAmount ?? 0),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);

$today = date('Y-m-d');
?>
<form method="post" action="<?= e($formAction) ?>" class="jf-card jf-form jf-form-stack"
      x-data="orderForm()" novalidate>
    <?= csrf_field() ?>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="customer_id">Pelanggan <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('customer_id') ? ' is-invalid' : '' ?>" id="customer_id" name="customer_id" required>
                <option value="0">— Pilih pelanggan —</option>
                <?php $currentCustomer = (int) $val('customer_id', $order?->customerId ?? 0); ?>
                <?php foreach ($customers as $cust): ?>
                    <option value="<?= (int) $cust->id ?>" <?= $currentCustomer === $cust->id ? 'selected' : '' ?>>
                        <?= e($cust->name) ?><?= $cust->type === 'perusahaan' ? ' (Perusahaan)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('customer_id')): ?><p class="jf-field-error"><?= e($err('customer_id')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="order_date">Tanggal Pesanan <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('order_date') ? ' is-invalid' : '' ?>" type="date" id="order_date" name="order_date"
                   value="<?= e((string) $val('order_date', $order?->orderDate ?? $today)) ?>" required>
            <?php if ($err('order_date')): ?><p class="jf-field-error"><?= e($err('order_date')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="due_date">Tenggat / Deadline <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('due_date') ? ' is-invalid' : '' ?>" type="date" id="due_date" name="due_date"
                   value="<?= e((string) $val('due_date', $order?->dueDate ?? $today)) ?>" required>
            <?php if ($err('due_date')): ?><p class="jf-field-error"><?= e($err('due_date')) ?></p><?php endif; ?>
        </div>
    </div>

    <!-- Editor item -->
    <div class="jf-fieldset-plain">
        <div class="jf-detail-head">
            <h2 class="jf-card-title jf-mb-0">Item Pesanan</h2>
            <button type="button" class="jf-btn jf-btn--sm jf-btn--primary" @click="addRow()">+ Tambah Item</button>
        </div>

        <?php if ($variantOptions === []): ?>
            <p class="jf-field-error">Belum ada varian produk aktif. Tambahkan varian (warna/ukuran + harga) di Katalog Produk sebelum membuat pesanan.</p>
        <?php else: ?>
            <div class="jf-so-header">
                <span>Produk &amp; varian</span>
                <span class="jf-so-qty">Jumlah</span>
                <span class="jf-so-line jf-text-right">Subtotal</span>
                <span></span>
            </div>

            <template x-for="(row, i) in rows" :key="row._k">
                <div class="jf-so-row">
                    <select class="jf-input" :name="'items[' + i + '][variant_id]'" x-model="row.variant_id" required>
                        <option value="">— Pilih varian —</option>
                        <?php foreach ($variantOptions as $opt): ?>
                            <option value="<?= (int) $opt['id'] ?>"><?= e($opt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input class="jf-input jf-so-qty" type="number" inputmode="numeric" min="1" step="1"
                           :name="'items[' + i + '][quantity]'" x-model.number="row.quantity" aria-label="Jumlah">
                    <span class="jf-so-line jf-text-right" x-text="money(lineTotal(row))"></span>
                    <button type="button" class="jf-btn jf-btn--sm jf-btn--ghost" @click="removeRow(i)"
                            :disabled="rows.length === 1" aria-label="Hapus item">Hapus</button>
                </div>
            </template>

            <?php if ($itemErrors !== []): ?>
                <?php foreach ($itemErrors as $itemError): ?>
                    <p class="jf-field-error"><?= e($itemError) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>

            <noscript><p class="jf-hint">Aktifkan JavaScript untuk menyusun item. Tanpa JS, total tetap dihitung server saat menyimpan.</p></noscript>
        <?php endif; ?>
    </div>

    <!-- Nilai uang -->
    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="discount_amount">Diskon (Rp)</label>
            <input class="jf-input<?= $err('discount_amount') ? ' is-invalid' : '' ?>" type="number" id="discount_amount" name="discount_amount"
                   min="0" step="1" value="<?= e((string) $val('discount_amount', $order?->discountAmount ?? 0)) ?>" x-model.number="discount">
            <?php if ($err('discount_amount')): ?><p class="jf-field-error"><?= e($err('discount_amount')) ?></p><?php endif; ?>
        </div>
        <div class="jf-field">
            <label class="jf-label" for="additional_cost">Biaya Tambahan (Rp)</label>
            <input class="jf-input<?= $err('additional_cost') ? ' is-invalid' : '' ?>" type="number" id="additional_cost" name="additional_cost"
                   min="0" step="1" value="<?= e((string) $val('additional_cost', $order?->additionalCost ?? 0)) ?>" x-model.number="additional">
            <?php if ($err('additional_cost')): ?><p class="jf-field-error"><?= e($err('additional_cost')) ?></p><?php endif; ?>
        </div>
        <div class="jf-field">
            <label class="jf-label" for="dp_amount">Uang Muka (Rp)</label>
            <input class="jf-input<?= $err('dp_amount') ? ' is-invalid' : '' ?>" type="number" id="dp_amount" name="dp_amount"
                   min="0" step="1" value="<?= e((string) $val('dp_amount', $order?->dpAmount ?? 0)) ?>" x-model.number="dp">
            <?php if ($err('dp_amount')): ?><p class="jf-field-error"><?= e($err('dp_amount')) ?></p><?php endif; ?>
            <p class="jf-hint">Produksi hanya dibuka bila DP &ge; 30% total (<?= e(number_format(SalesOrder::DP_MIN_RATIO * 100, 0)) ?>%).</p>
        </div>
    </div>

    <!-- Prakiraan (client-side; server menghitung ulang) -->
    <div class="jf-so-summary" aria-live="polite">
        <div class="jf-so-sumrow"><span>Subtotal</span><span x-text="money(subtotal())"></span></div>
        <div class="jf-so-sumrow"><span>Total</span><span x-text="money(grand())"></span></div>
        <div class="jf-so-sumrow"><span>Sisa pembayaran</span><span x-text="money(remaining())"></span></div>
        <div class="jf-so-sumrow jf-so-dp">
            <span>DP <em x-text="dpPct().toFixed(0) + '%'"></em></span>
            <span :class="dpOk() ? 'jf-text-success' : 'jf-text-warning'" x-text="dpOk() ? 'Gerbang produksi terpenuhi' : 'DP belum 30%'"></span>
        </div>
        <p class="jf-hint">Prakiraan untuk bantuan input. Angka final dihitung sistem dari harga varian saat menyimpan.</p>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="design_notes">Catatan Desain</label>
        <textarea class="jf-input jf-textarea" id="design_notes" name="design_notes" rows="3" maxlength="2000"
                  placeholder="Detail desain, warna khusus, catatan produksi."><?= e((string) $val('design_notes', $order?->designNotes ?? '')) ?></textarea>
        <?php if ($err('design_notes')): ?><p class="jf-field-error"><?= e($err('design_notes')) ?></p><?php endif; ?>
    </div>

    <?php if ($err('status')): ?><p class="jf-field-error"><?= e($err('status')) ?></p><?php endif; ?>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' ?></button>
        <a class="jf-btn jf-btn--ghost" href="<?= $isEdit ? e(route('sales_orders.show', ['id' => $order->id])) : e(route('sales_orders.index')) ?>">Batal</a>
    </div>
</form>

<script id="order-init" type="application/json"><?= $initData ?></script>
<script>
    function orderForm() {
        var seed = JSON.parse(document.getElementById('order-init').textContent);
        var fmt = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
        var counter = 0;
        var rows = seed.rows.map(function (r) {
            return { variant_id: String(r.variant_id || ''), quantity: parseInt(r.quantity, 10) || 1, _k: ++counter };
        });

        return {
            rows: rows,
            prices: seed.prices,
            discount: seed.discount || 0,
            additional: seed.additional || 0,
            dp: seed.dp || 0,
            money: function (n) { return fmt.format(n || 0); },
            priceOf: function (row) { return parseFloat(this.prices[String(row.variant_id)] || 0); },
            lineTotal: function (row) { return this.priceOf(row) * (parseInt(row.quantity, 10) || 0); },
            subtotal: function () { return this.rows.reduce((s, r) => s + this.lineTotal(r), 0); },
            grand: function () { return Math.max(0, this.subtotal() - (this.discount || 0) + (this.additional || 0)); },
            remaining: function () { return Math.max(0, this.grand() - (this.dp || 0)); },
            dpPct: function () { var g = this.grand(); return g > 0 ? (this.dp || 0) / g * 100 : 0; },
            dpOk: function () { var g = this.grand(); return g > 0 && (this.dp || 0) >= Math.round(g * 0.30); },
            addRow: function () { this.rows.push({ variant_id: '', quantity: 1, _k: ++counter }); },
            removeRow: function (i) { if (this.rows.length > 1) { this.rows.splice(i, 1); } },
        };
    }
</script>
