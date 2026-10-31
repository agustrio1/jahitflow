<?php

use App\Models\Customer;

/**
 * Detail pelanggan. Tautan edit/status muncul sesuai role, tapi
 * otorisasi sebenarnya tetap dijaga policy di controller.
 *
 * @var App\Core\View $view
 * @var Customer $customer
 * @var bool $canSetStatus
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');
?>
<section class="jf-card">
    <div class="jf-detail-head">
        <div>
            <span class="jf-badge <?= $customer->isActive ? 'jf-badge--success' : 'jf-badge--warning' ?>">
                <?= $customer->isActive ? 'Aktif' : 'Nonaktif' ?>
            </span>
            <span class="jf-badge jf-badge--info"><?= e($customer->typeLabel()) ?></span>
        </div>
        <div class="jf-actions">
            <a class="jf-btn jf-btn--sm" href="<?= e(route('customers.edit', ['id' => $customer->id])) ?>">Ubah</a>
            <?php if ($canSetStatus): ?>
                <form method="post" action="<?= e(route('customers.setStatus', ['id' => $customer->id, 'status' => $customer->isActive ? 'nonaktif' : 'aktif'])) ?>"
                      class="jf-inline"
                      <?= $customer->isActive ? 'onsubmit="return confirm(\'Nonaktifkan pelanggan ini? Riwayat pesanan tetap tersimpan.\')"' : '' ?>>
                    <?= csrf_field() ?>
                    <button type="submit" class="jf-btn jf-btn--sm <?= $customer->isActive ? 'jf-btn--danger' : 'jf-btn--primary' ?>">
                        <?= $customer->isActive ? 'Nonaktifkan' : 'Aktifkan kembali' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <dl class="jf-dl">
        <div class="jf-dl-row"><dt>Telepon</dt><dd><?= e($customer->phone) ?></dd></div>
        <div class="jf-dl-row"><dt>Kontak / PIC</dt><dd><?= e($customer->contactPerson ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Email</dt><dd><?= e($customer->email ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Kota</dt><dd><?= e($customer->city ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Alamat</dt><dd><?= e($customer->address ?? '—') ?></dd></div>
        <div class="jf-dl-row"><dt>Dicatat sejak</dt><dd><?= e(tanggal($customer->createdAt)) ?></dd></div>
    </dl>

    <?php if ($customer->notes): ?>
        <div class="jf-fieldset-plain">
            <h3 class="jf-dl-caption">Catatan</h3>
            <p class="jf-note"><?= nl2br(e($customer->notes)) ?></p>
        </div>
    <?php endif; ?>
</section>

<p class="jf-text-muted jf-sm">
    Riwayat pesanan pelanggan akan tersedia setelah modul Sales Order dibangun.
</p>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('customers.index')) ?>">← Kembali ke daftar</a>
</div>
