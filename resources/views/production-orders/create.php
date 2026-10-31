<?php

use App\Models\SalesOrder;

/**
 * Form pembuatan perintah produksi dari pesanan terkonfirmasi.
 * Target kuantitas dan tahap diambil sistem (snapshot konfigurasi) —
 * formulir hanya memilih penanggung jawab, tanggal target, dan catatan.
 *
 * @var App\Core\View $view
 * @var SalesOrder $salesOrder
 * @var list<array{id:int, name:string, role:string}> $pics
 * @var string $formAction
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
$val = static function (string $key, mixed $fallback = '') use ($oldInput): mixed {
    return array_key_exists($key, $oldInput) ? $oldInput[$key] : $fallback;
};

$generalErr = $err('sales_order') ?? $err('dp') ?? $err('existing') ?? $err('stages');
?>
<section class="jf-card">
    <h2 class="jf-card-title">Pesanan Sumber</h2>
    <dl class="jf-dl">
        <div class="jf-dl-row">
            <dt>Nomor pesanan</dt>
            <dd><a class="jf-link" href="<?= e(route('sales_orders.show', ['id' => $salesOrder->id])) ?>"><?= e($salesOrder->documentNumber) ?></a></dd>
        </div>
        <div class="jf-dl-row"><dt>Pelanggan</dt><dd><?= e($salesOrder->customerName ?? ('#' . $salesOrder->customerId)) ?></dd></div>
        <div class="jf-dl-row"><dt>Tenggat pesanan</dt><dd><?= e(tanggal($salesOrder->dueDate)) ?></dd></div>
        <div class="jf-dl-row"><dt>Total kuantitas</dt><dd><?= e((string) $salesOrder->totalQuantity()) ?> pcs · <?= e((string) count($salesOrder->items)) ?> varian</dd></div>
        <div class="jf-dl-row"><dt>Status</dt><dd><span class="jf-badge <?= e($salesOrder->statusBadge()) ?>"><?= e($salesOrder->statusLabel()) ?></span></dd></div>
    </dl>

    <p class="jf-note <?= $salesOrder->meetsDpGate() ? 'jf-text-success' : 'jf-text-warning' ?>">
        <?php if ($salesOrder->meetsDpGate()): ?>
            Gerbang produksi terpenuhi — DP sudah ≥ 30% dari total. Tahap produksi akan di-snapshot dari konfigurasi saat ini.
        <?php else: ?>
            Produksi belum dapat dibuka: DP minimal <?= e(money(round($salesOrder->grandTotal * SalesOrder::DP_MIN_RATIO, 2))) ?> (30% dari total).
        <?php endif; ?>
    </p>

    <?php if ($generalErr): ?><p class="jf-field-error"><?= e($generalErr) ?></p><?php endif; ?>
</section>

<form method="post" action="<?= e($formAction) ?>" class="jf-card jf-form jf-form-stack" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="sales_order_id" value="<?= (int) $salesOrder->id ?>">

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="assigned_to">Penanggung Jawab</label>
            <select class="jf-input<?= $err('assigned_to') ? ' is-invalid' : '' ?>" id="assigned_to" name="assigned_to">
                <option value="0">— Belum ditentukan —</option>
                <?php $currentPic = (int) $val('assigned_to', 0); ?>
                <?php foreach ($pics as $person): ?>
                    <option value="<?= (int) $person['id'] ?>" <?= $currentPic === (int) $person['id'] ? 'selected' : '' ?>>
                        <?= e($person['name']) ?> (<?= e($person['role']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('assigned_to')): ?><p class="jf-field-error"><?= e($err('assigned_to')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="target_date">Tanggal Target Selesai</label>
            <input class="jf-input<?= $err('target_date') ? ' is-invalid' : '' ?>" type="date" id="target_date" name="target_date"
                   value="<?= e((string) $val('target_date', $salesOrder->dueDate)) ?>">
            <p class="jf-hint">Kosongkan untuk mengikuti tenggat pesanan.</p>
            <?php if ($err('target_date')): ?><p class="jf-field-error"><?= e($err('target_date')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="description">Catatan Produksi</label>
        <textarea class="jf-input jf-textarea" id="description" name="description" rows="3" maxlength="255"
                  placeholder="Instruksi kerja, kendala khusus, prioritas."><?= e((string) $val('description', '')) ?></textarea>
        <?php if ($err('description')): ?><p class="jf-field-error"><?= e($err('description')) ?></p><?php endif; ?>
    </div>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary">Buat Perintah Produksi</button>
        <a class="jf-btn jf-btn--ghost" href="<?= e(route('sales_orders.show', ['id' => $salesOrder->id])) ?>">Batal</a>
    </div>
</form>
