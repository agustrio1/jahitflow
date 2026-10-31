<?php

use App\Models\RawMaterial;
use App\Models\StockMovement;
use App\Support\StockLedger;

/**
 * Detail bahan baku: saldo + peringatan minimum, form pencatatan pergerakan
 * (hanya untuk peran yang boleh), dan riwayat ledger berpaginasi.
 *
 * Saldo TIDAK diedit langsung di sini — setiap perubahan melewati ledger
 * (StockService: FOR UPDATE + StockLedger anti-negatif + audit).
 *
 * @var App\Core\View $view
 * @var RawMaterial $material
 * @var list<StockMovement> $history
 * @var App\Support\Paginator $paginator
 * @var bool $canManage
 * @var bool $canRecord
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
$val = static function (string $key, mixed $fallback = '') use ($oldInput): mixed {
    return array_key_exists($key, $oldInput) ? $oldInput[$key] : $fallback;
};
$currentDirection = (string) $val('direction', StockLedger::IN);
$errorBanner = array_merge(
    $fieldErrors['direction'] ?? [],
    $fieldErrors['quantity'] ?? [],
    $fieldErrors['reason'] ?? [],
    $fieldErrors['reference_type'] ?? [],
    $fieldErrors['reference_id'] ?? [],
);

$refTypes = [
    ''                => '— (tanpa rujukan)',
    'production_order' => 'Perintah Produksi',
    'purchase'        => 'Pembelian',
    'manual'          => 'Manual',
    'other'           => 'Lainnya',
];
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= $material->isActive ? 'jf-badge--success' : 'jf-badge--danger' ?>">
                <?= $material->isActive ? 'Aktif' : 'Nonaktif' ?>
            </span>
            <?php if ($material->isLowStock()): ?>
                <span class="jf-badge jf-badge--warning">Stok Menipis</span>
            <?php endif; ?>
        </div>
        <div class="jf-actions">
            <?php if ($canManage): ?>
                <a class="jf-btn jf-btn--sm" href="<?= e(route('materials.edit', ['id' => $material->id])) ?>">Ubah</a>
                <form method="post" action="<?= e(route('materials.setStatus', ['id' => $material->id, 'status' => $material->isActive ? 'nonaktif' : 'aktif'])) ?>" class="jf-inline"
                      onsubmit="return confirm('<?= $material->isActive ? 'Nonaktifkan' : 'Aktifkan' ?> bahan ini?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm jf-btn--ghost">
                        <?= $material->isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <dl class="jf-dl">
        <div class="jf-dl-row"><dt>Kode</dt><dd><span class="jf-mono"><?= e($material->code) ?></span></dd></div>
        <div class="jf-dl-row"><dt>Jenis</dt><dd><?= e($material->typeLabel()) ?></dd></div>
        <div class="jf-dl-row">
            <dt>Saldo stok</dt>
            <dd class="<?= $material->isLowStock() ? 'jf-text-warning' : '' ?>"><strong><?= e($material->stockWithUnit()) ?></strong></dd>
        </div>
        <div class="jf-dl-row"><dt>Stok minimum</dt><dd><?= e(RawMaterial::formatQty($material->minStock)) ?> <?= e($material->unit) ?></dd></div>
        <div class="jf-dl-row"><dt>Dibuat</dt><dd><?= e(tanggal($material->createdAt)) ?></dd></div>
        <?php if ($material->notes): ?>
            <div class="jf-dl-row"><dt>Catatan</dt><dd><?= nl2br(e($material->notes)) ?></dd></div>
        <?php endif; ?>
    </dl>
</section>

<?php if ($canRecord): ?>
    <section class="jf-card">
        <h2 class="jf-card-title">Catat Pergerakan Stok</h2>

        <?php foreach ($errorBanner as $message): ?>
            <p class="jf-field-error"><?= e($message) ?></p>
        <?php endforeach; ?>

        <form method="post" action="<?= e(route('materials.stock', ['id' => $material->id])) ?>" class="jf-form jf-form-stack" novalidate>
            <?= csrf_field() ?>
            <div class="jf-form-grid">
                <div class="jf-field">
                    <label class="jf-label" for="direction">Arah <span class="jf-req">*</span></label>
                    <select class="jf-input<?= $err('direction') ? ' is-invalid' : '' ?>" id="direction" name="direction" required>
                        <option value="<?= StockLedger::IN ?>" <?= $currentDirection === StockLedger::IN ? 'selected' : '' ?>>Penerimaan (stok masuk)</option>
                        <option value="<?= StockLedger::OUT ?>" <?= $currentDirection === StockLedger::OUT ? 'selected' : '' ?>>Pemakaian (stok keluar)</option>
                        <option value="<?= StockLedger::ADJUSTMENT ?>" <?= $currentDirection === StockLedger::ADJUSTMENT ? 'selected' : '' ?>>Penyesuaian (±)</option>
                    </select>
                    <?php if ($err('direction')): ?><p class="jf-field-error"><?= e($err('direction')) ?></p><?php endif; ?>
                </div>

                <div class="jf-field">
                    <label class="jf-label" for="quantity">Jumlah (<?= e($material->unit) ?>) <span class="jf-req">*</span></label>
                    <input class="jf-input<?= $err('quantity') ? ' is-invalid' : '' ?>" type="text" inputmode="decimal" id="quantity" name="quantity"
                           value="<?= e((string) $val('quantity')) ?>" required
                           placeholder="mis. 12,5 (penyesuaian boleh diawali minus)">
                    <p class="jf-hint">Untuk penyesuaian, gunakan tanda minus (−) bila mengurangi.</p>
                    <?php if ($err('quantity')): ?><p class="jf-field-error"><?= e($err('quantity')) ?></p><?php endif; ?>
                </div>

                <div class="jf-field">
                    <label class="jf-label" for="reason">Alasan <span class="jf-req">*</span></label>
                    <input class="jf-input<?= $err('reason') ? ' is-invalid' : '' ?>" type="text" id="reason" name="reason"
                           value="<?= e((string) $val('reason')) ?>" maxlength="190" required
                           placeholder="mis. Produksi PP-2026-0007 / pembelian / susut">
                    <?php if ($err('reason')): ?><p class="jf-field-error"><?= e($err('reason')) ?></p><?php endif; ?>
                </div>

                <div class="jf-field">
                    <label class="jf-label" for="reference_type">Jenis Rujukan</label>
                    <select class="jf-input<?= $err('reference_type') ? ' is-invalid' : '' ?>" id="reference_type" name="reference_type">
                        <?php $currentRef = (string) $val('reference_type', ''); ?>
                        <?php foreach ($refTypes as $code => $label): ?>
                            <option value="<?= e($code) ?>" <?= $currentRef === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($err('reference_type')): ?><p class="jf-field-error"><?= e($err('reference_type')) ?></p><?php endif; ?>
                </div>

                <div class="jf-field">
                    <label class="jf-label" for="reference_id">ID Rujukan</label>
                    <input class="jf-input<?= $err('reference_id') ? ' is-invalid' : '' ?>" type="number" min="1" step="1" id="reference_id" name="reference_id"
                           value="<?= e((string) $val('reference_id')) ?>" placeholder="opsional">
                    <?php if ($err('reference_id')): ?><p class="jf-field-error"><?= e($err('reference_id')) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="jf-form-actions">
                <button type="submit" class="jf-btn jf-btn--primary">Simpan Pergerakan</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<section class="jf-card">
    <h2 class="jf-card-title">Riwayat Pergerakan</h2>

    <?php if ($history === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pergerakan</p>
            <p class="jf-empty-text">
                <?= $canRecord ? 'Catat penerimaan pertama untuk mengisi stok bahan ini.' : 'Belum ada transaksi stok untuk bahan ini.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="jf-table-wrap">
            <table class="jf-table">
                <thead>
                    <tr>
                        <th scope="col">Waktu</th>
                        <th scope="col">Arah</th>
                        <th scope="col" class="jf-text-right">Perubahan</th>
                        <th scope="col" class="jf-text-right">Saldo</th>
                        <th scope="col">Alasan</th>
                        <th scope="col">PIC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $mv): ?>
                        <tr>
                            <td class="jf-sm jf-text-muted"><?= e(tanggal($mv->createdAt)) ?></td>
                            <td><span class="jf-badge <?= e($mv->directionBadge()) ?>"><?= e($mv->directionLabel()) ?></span></td>
                            <td class="jf-text-right jf-mono"><?= e($mv->signedDelta()) ?></td>
                            <td class="jf-text-right jf-sm">
                                <span class="jf-text-muted"><?= e(RawMaterial::formatQty($mv->balanceBefore)) ?></span>
                                → <strong><?= e(RawMaterial::formatQty($mv->balanceAfter)) ?></strong>
                            </td>
                            <td class="jf-sm"><?= e($mv->reason) ?></td>
                            <td class="jf-sm jf-text-muted"><?= e($mv->createdByName ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('materials.show', ['id' => $material->id]),
            'query' => [],
        ]) ?>
    <?php endif; ?>
</section>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('materials.index')) ?>">← Kembali ke daftar bahan</a>
</div>
