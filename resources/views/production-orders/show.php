<?php

use App\Models\ProductionOrder;
use App\Support\ProductionStageMachine;

/**
 * Papan tahap perintah produksi (§8.E). Setiap tahap adalah satu form:
 * tombol aksi memakai formaction agar "Mulai/Selesai/Buka" ikut menyimpan
 * target, realisasi, PIC, dan catatan yang sedang diketik. Kelayakan transisi
 * divalidasi server (ProductionStageMachine); disable tombol hanya petunjuk UI.
 *
 * @var App\Core\View $view
 * @var ProductionOrder $order
 * @var list<array{id:int, name:string, role:string}> $pics
 * @var bool $canManage
 * @var bool $canProgress
 * @var array<string, list<string>> $fieldErrors
 */
$view->extend('layouts.app');

$errorBanner = array_merge(
    $fieldErrors['status'] ?? [],
    $fieldErrors['realized_qty'] ?? [],
    $fieldErrors['target_qty'] ?? [],
    $fieldErrors['notes'] ?? [],
);

$isClosed = $order->status === ProductionOrder::STATUS_COMPLETED || $order->status === ProductionOrder::STATUS_CANCELLED;
$pct = $order->progressPercent();
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= e($order->statusBadge()) ?>"><?= e($order->statusLabel()) ?></span>
            <?php if ($order->isOverdue()): ?>
                <span class="jf-badge jf-badge--danger">Lewat target</span>
            <?php endif; ?>
        </div>
        <div class="jf-actions">
            <?php if ($canManage && !$isClosed): ?>
                <form method="post" action="<?= e(route('produksi.cancel', ['id' => $order->id])) ?>" class="jf-inline"
                      onsubmit="return confirm('Batalkan perintah produksi ini? Progres tahap akan terkunci.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm jf-btn--danger">Batalkan</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="jf-progress jf-progress--lg" role="progressbar" aria-valuenow="<?= e((string) $pct) ?>" aria-valuemin="0" aria-valuemax="100">
        <span class="jf-progress__bar" style="width: <?= e((string) $pct) ?>%"></span>
    </div>
    <p class="jf-text-muted jf-sm">Progres keseluruhan: <?= e((string) $pct) ?>%</p>

    <?php if ($errorBanner !== []): ?>
        <?php foreach ($errorBanner as $message): ?>
            <p class="jf-field-error"><?= e($message) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <dl class="jf-dl">
        <div class="jf-dl-row">
            <dt>Pesanan sumber</dt>
            <dd><a class="jf-link" href="<?= e(route('sales_orders.show', ['id' => $order->salesOrderId])) ?>"><?= e($order->salesOrderNumber ?? ('#' . $order->salesOrderId)) ?></a></dd>
        </div>
        <div class="jf-dl-row"><dt>Pelanggan</dt><dd><?= e($order->customerName ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Target kuantitas</dt><dd><?= e((string) $order->targetQuantity) ?> pcs</dd></div>
        <div class="jf-dl-row">
            <dt>Tanggal target</dt>
            <dd class="<?= $order->isOverdue() ? 'jf-text-warning' : '' ?>"><?= e(tanggal($order->targetDate)) ?></dd>
        </div>
        <div class="jf-dl-row"><dt>Penanggung jawab</dt><dd><?= e($order->assignedUserName ?? 'Belum ditentukan') ?></dd></div>
        <?php if ($order->description): ?>
            <div class="jf-dl-row"><dt>Catatan produksi</dt><dd><?= nl2br(e($order->description)) ?></dd></div>
        <?php endif; ?>
    </dl>
</section>

<section class="jf-card">
    <h2 class="jf-card-title">Papan Tahap</h2>

    <?php if ($order->stages === []): ?>
        <p class="jf-empty-text">Belum ada tahap. Tahap di-snapshot saat perintah dibuat — periksa konfigurasi tahap produksi.</p>
    <?php elseif (!$canProgress): ?>
        <!-- Tampilan baca-saja untuk peran tanpa akses progres. -->
        <ol class="jf-stage-list">
            <?php foreach ($order->stages as $stage): ?>
                <li class="jf-stage jf-stage--readonly">
                    <div class="jf-stage-head">
                        <span class="jf-stage-pos"><?= (int) $stage->position ?></span>
                        <strong><?= e($stage->stageName) ?></strong>
                        <span class="jf-badge <?= e($stage->statusBadge()) ?>"><?= e($stage->statusLabel()) ?></span>
                    </div>
                    <div class="jf-text-muted jf-sm">
                        Realisasi <?= e((string) $stage->realizedQty) ?> / <?= e((string) $stage->targetQty) ?> pcs
                        · PIC <?= e($stage->assignedUserName ?? '—') ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <ol class="jf-stage-list">
            <?php foreach ($order->stages as $stage): ?>
                <?php
                $baseUrl = route('produksi.stage', ['stageId' => $stage->id, 'action' => 'update']);
                $urlFor = static fn(string $a): string => route('produksi.stage', ['stageId' => $stage->id, 'action' => $a]);
                $isPending = $stage->status === ProductionStageMachine::PENDING;
                $isRunning = $stage->status === ProductionStageMachine::IN_PROGRESS;
                $isDone = $stage->status === ProductionStageMachine::DONE;
                $closed = $order->status === ProductionOrder::STATUS_CANCELLED;
                ?>
                <li class="jf-stage<?= $isDone ? ' is-done' : '' ?>">
                    <form method="post" action="<?= e($baseUrl) ?>" class="jf-stage-form">
                        <?= csrf_field() ?>
                        <div class="jf-stage-head">
                            <span class="jf-stage-pos"><?= (int) $stage->position ?></span>
                            <strong class="jf-stage-name"><?= e($stage->stageName) ?></strong>
                            <span class="jf-badge <?= e($stage->statusBadge()) ?>"><?= e($stage->statusLabel()) ?></span>
                        </div>

                        <div class="jf-stage-grid">
                            <div class="jf-field">
                                <label class="jf-label" for="target-<?= (int) $stage->id ?>">Target (pcs)</label>
                                <input class="jf-input" type="number" min="0" step="1" id="target-<?= (int) $stage->id ?>"
                                       name="target_qty" value="<?= e((string) $stage->targetQty) ?>">
                            </div>
                            <div class="jf-field">
                                <label class="jf-label" for="realized-<?= (int) $stage->id ?>">Realisasi (pcs)</label>
                                <input class="jf-input" type="number" min="0" step="1" id="realized-<?= (int) $stage->id ?>"
                                       name="realized_qty" value="<?= e((string) $stage->realizedQty) ?>">
                            </div>
                            <div class="jf-field">
                                <label class="jf-label" for="pic-<?= (int) $stage->id ?>">PIC Tahap</label>
                                <select class="jf-input" id="pic-<?= (int) $stage->id ?>" name="assigned_to">
                                    <option value="0">—</option>
                                    <?php foreach ($pics as $person): ?>
                                        <option value="<?= (int) $person['id'] ?>" <?= $stage->assignedTo === (int) $person['id'] ? 'selected' : '' ?>>
                                            <?= e($person['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="jf-field">
                            <label class="jf-label" for="notes-<?= (int) $stage->id ?>">Catatan Kendala</label>
                            <input class="jf-input" type="text" maxlength="255" id="notes-<?= (int) $stage->id ?>"
                                   name="notes" value="<?= e((string) ($stage->notes ?? '')) ?>"
                                   placeholder="Kendala / catatan tahap ini">
                        </div>

                        <div class="jf-stage-times jf-text-muted jf-sm">
                            <?php if ($stage->startedAt): ?>Dikerjakan: <?= e(tanggal($stage->startedAt)) ?><?php endif; ?>
                            <?php if ($stage->finishedAt): ?> · Selesai: <?= e(tanggal($stage->finishedAt)) ?><?php endif; ?>
                        </div>

                        <?php if (!$closed): ?>
                            <div class="jf-stage-actions">
                                <button type="submit" class="jf-btn jf-btn--sm jf-btn--ghost">Simpan Progres</button>
                                <button type="submit" class="jf-btn jf-btn--sm" formaction="<?= e($urlFor('start')) ?>"
                                        <?= $isPending ? '' : 'disabled' ?>>Mulai</button>
                                <button type="submit" class="jf-btn jf-btn--sm jf-btn--primary" formaction="<?= e($urlFor('complete')) ?>"
                                        <?= $isRunning ? '' : 'disabled' ?>>Selesai</button>
                                <button type="submit" class="jf-btn jf-btn--sm jf-btn--danger" formaction="<?= e($urlFor('reopen')) ?>"
                                        <?= $isDone ? '' : 'disabled' ?>>Buka Lagi</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('produksi.index')) ?>">← Kembali ke daftar produksi</a>
    <a class="jf-btn jf-btn--sm jf-btn--icon jf-push" href="<?= e(route('produksi.unduh.pdf', ['id' => $order->id])) ?>" target="_blank" rel="noopener">
        <?= icon('printer') ?>Cetak / PDF
    </a>
    <a class="jf-btn jf-btn--sm jf-btn--icon" href="<?= e(route('produksi.unduh.csv', ['id' => $order->id])) ?>" rel="nofollow" download>
        <?= icon('download') ?>Unduh CSV
    </a>
</div>
