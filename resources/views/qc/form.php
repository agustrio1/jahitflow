<?php

use App\Models\QcInspection;

/**
 * Form catat pemeriksaan QC (hanya tambah — pemeriksaan bersifat log).
 *
 * @var App\Core\View $view
 * @var QcInspection|null $inspection
 * @var list<array{id:int, document_number:string, customer_name:string, target_quantity:int}> $producibleOptions
 * @var string $formAction
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
$val = static function (string $key, mixed $fallback = '') use ($oldInput): mixed {
    return array_key_exists($key, $oldInput) ? $oldInput[$key] : $fallback;
};
$currentResult = (string) $val('result', QcInspection::RESULT_LOLOS);
$currentOrder = (int) $val('production_order_id', $inspection?->productionOrderId ?? 0);
$hasOptions = $producibleOptions !== [];
?>
<?php if (!$hasOptions): ?>
    <div class="jf-card">
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada perintah produksi yang dapat diperiksa</p>
            <p class="jf-empty-text">
                QC hanya dapat mencatat pemeriksaan atas perintah produksi yang Berjalan atau Selesai.
                Buat/mulai perintah produksi lebih dulu.
            </p>
            <a class="jf-btn jf-btn--ghost" href="<?= e(route('produksi.index')) ?>">Lihat Perintah Produksi</a>
        </div>
    </div>
<?php else: ?>
<form method="post" action="<?= e($formAction) ?>" class="jf-card jf-form jf-form-stack" novalidate>
    <?= csrf_field() ?>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="production_order_id">Perintah Produksi <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('production_order_id') ? ' is-invalid' : '' ?>" id="production_order_id" name="production_order_id" required>
                <option value="0">— Pilih perintah produksi —</option>
                <?php foreach ($producibleOptions as $opt): ?>
                    <option value="<?= (int) $opt['id'] ?>" <?= $currentOrder === (int) $opt['id'] ? 'selected' : '' ?>>
                        <?= e($opt['document_number']) ?> — <?= e($opt['customer_name']) ?> (target <?= (int) $opt['target_quantity'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('production_order_id')): ?><p class="jf-field-error"><?= e($err('production_order_id')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="result">Hasil Pemeriksaan <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('result') ? ' is-invalid' : '' ?>" id="result" name="result" required>
                <?php
                $resultOptions = [
                    QcInspection::RESULT_LOLOS => 'Lolos (tanpa cacat)',
                    QcInspection::RESULT_PERBAIKAN => 'Perlu Perbaikan (ada cacat, dapat diperbaiki)',
                    QcInspection::RESULT_GAGAL => 'Gagal (ada cacat, ditolak)',
                ];
                foreach ($resultOptions as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $currentResult === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($err('result')): ?><p class="jf-field-error"><?= e($err('result')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-form-grid jf-form-grid--qc-qty">
        <div class="jf-field">
            <label class="jf-label" for="inspected_qty">Jumlah Diperiksa <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('inspected_qty') ? ' is-invalid' : '' ?>" type="number" min="1" step="1" id="inspected_qty" name="inspected_qty"
                   value="<?= e((string) $val('inspected_qty', '')) ?>" required>
            <?php if ($err('inspected_qty')): ?><p class="jf-field-error"><?= e($err('inspected_qty')) ?></p><?php endif; ?>
        </div>
        <div class="jf-field">
            <label class="jf-label" for="passed_qty">Lolos</label>
            <input class="jf-input<?= $err('passed_qty') ? ' is-invalid' : '' ?>" type="number" min="0" step="1" id="passed_qty" name="passed_qty"
                   value="<?= e((string) $val('passed_qty', '0')) ?>">
            <?php if ($err('passed_qty')): ?><p class="jf-field-error"><?= e($err('passed_qty')) ?></p><?php endif; ?>
        </div>
        <div class="jf-field">
            <label class="jf-label" for="failed_qty">Gagal</label>
            <input class="jf-input<?= $err('failed_qty') ? ' is-invalid' : '' ?>" type="number" min="0" step="1" id="failed_qty" name="failed_qty"
                   value="<?= e((string) $val('failed_qty', '0')) ?>">
            <?php if ($err('failed_qty')): ?><p class="jf-field-error"><?= e($err('failed_qty')) ?></p><?php endif; ?>
        </div>
    </div>
    <p class="jf-hint">Batas: <strong>lolos + gagal tidak boleh melebihi jumlah diperiksa</strong>. Hasil "Lolos" mensyaratkan jumlah gagal = 0.</p>

    <div class="jf-field">
        <label class="jf-label" for="defect_note">Catatan Cacat</label>
        <textarea class="jf-input jf-textarea<?= $err('defect_note') ? ' is-invalid' : '' ?>" id="defect_note" name="defect_note" rows="2"
                  maxlength="500" placeholder="Jenis & letak cacat (wajib bila ada barang gagal)."><?= e((string) $val('defect_note', '')) ?></textarea>
        <?php if ($err('defect_note')): ?><p class="jf-field-error"><?= e($err('defect_note')) ?></p><?php endif; ?>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="rework_action">Tindakan Perbaikan</label>
        <textarea class="jf-input jf-textarea<?= $err('rework_action') ? ' is-invalid' : '' ?>" id="rework_action" name="rework_action" rows="2"
                  maxlength="500" placeholder="Rencana perbaikan (wajib untuk hasil &quot;Perlu Perbaikan&quot;)."><?= e((string) $val('rework_action', '')) ?></textarea>
        <?php if ($err('rework_action')): ?><p class="jf-field-error"><?= e($err('rework_action')) ?></p><?php endif; ?>
    </div>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary">Simpan Pemeriksaan</button>
        <a class="jf-btn jf-btn--ghost" href="<?= e(route('qc.index')) ?>">Batal</a>
    </div>
</form>
<?php endif; ?>
