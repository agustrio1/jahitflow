<?php

use App\Models\Customer;

/**
 * Form tambah/edit pelanggan (satu template untuk dua mode).
 * Nilai field: input lama (setelah gagal validasi) menang, lalu
 * data pelanggan (mode edit), lalu kosong.
 *
 * @var App\Core\View $view
 * @var Customer|null $customer
 * @var string $formAction
 * @var string $pageTitle
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.app');

$isEdit = $customer !== null;

$val = static function (string $key, mixed $fallback = '') use ($oldInput, $customer): mixed {
    if (array_key_exists($key, $oldInput)) {
        return $oldInput[$key];
    }

    return $fallback;
};

$currentType = $val('type', $customer?->type ?? Customer::TYPE_PERORANGAN);
$err = static fn(string $key): ?string => $fieldErrors[$key][0] ?? null;
?>
<form method="post" action="<?= e($formAction) ?>" class="jf-card jf-form jf-form-stack" novalidate>
    <?= csrf_field() ?>

    <div class="jf-form-grid">
        <div class="jf-field">
            <label class="jf-label" for="name">Nama Pelanggan <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('name') ? ' is-invalid' : '' ?>" type="text" id="name" name="name"
                   value="<?= e((string) $val('name', $customer?->name)) ?>" maxlength="150" required>
            <?php if ($err('name')): ?><p class="jf-field-error"><?= e($err('name')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="type">Tipe <span class="jf-req">*</span></label>
            <select class="jf-input<?= $err('type') ? ' is-invalid' : '' ?>" id="type" name="type" required>
                <option value="perorangan" <?= $currentType === 'perorangan' ? 'selected' : '' ?>>Perorangan</option>
                <option value="perusahaan" <?= $currentType === 'perusahaan' ? 'selected' : '' ?>>Perusahaan</option>
            </select>
            <?php if ($err('type')): ?><p class="jf-field-error"><?= e($err('type')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="phone">Telepon <span class="jf-req">*</span></label>
            <input class="jf-input<?= $err('phone') ? ' is-invalid' : '' ?>" type="tel" id="phone" name="phone"
                   value="<?= e((string) $val('phone', $customer?->phone)) ?>" maxlength="20" required>
            <?php if ($err('phone')): ?><p class="jf-field-error"><?= e($err('phone')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="contact_person">Nama Kontak / PIC</label>
            <input class="jf-input<?= $err('contact_person') ? ' is-invalid' : '' ?>" type="text" id="contact_person"
                   name="contact_person" value="<?= e((string) $val('contact_person', $customer?->contactPerson)) ?>" maxlength="100">
            <p class="jf-hint">Wajib untuk pelanggan perusahaan.</p>
            <?php if ($err('contact_person')): ?><p class="jf-field-error"><?= e($err('contact_person')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="email">Email</label>
            <input class="jf-input<?= $err('email') ? ' is-invalid' : '' ?>" type="email" id="email" name="email"
                   value="<?= e((string) $val('email', $customer?->email)) ?>" maxlength="150">
            <?php if ($err('email')): ?><p class="jf-field-error"><?= e($err('email')) ?></p><?php endif; ?>
        </div>

        <div class="jf-field">
            <label class="jf-label" for="city">Kota</label>
            <input class="jf-input<?= $err('city') ? ' is-invalid' : '' ?>" type="text" id="city" name="city"
                   value="<?= e((string) $val('city', $customer?->city)) ?>" maxlength="100">
            <?php if ($err('city')): ?><p class="jf-field-error"><?= e($err('city')) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="address">Alamat</label>
        <textarea class="jf-input jf-textarea" id="address" name="address" rows="2"
                  maxlength="255"><?= e((string) $val('address', $customer?->address)) ?></textarea>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="notes">Catatan</label>
        <textarea class="jf-input jf-textarea" id="notes" name="notes" rows="3"
                  maxlength="2000"><?= e((string) $val('notes', $customer?->notes)) ?></textarea>
        <?php if ($err('notes')): ?><p class="jf-field-error"><?= e($err('notes')) ?></p><?php endif; ?>
    </div>

    <div class="jf-form-actions">
        <button type="submit" class="jf-btn jf-btn--primary"><?= $isEdit ? 'Simpan Perubahan' : 'Simpan Pelanggan' ?></button>
        <a class="jf-btn jf-btn--ghost" href="<?= $isEdit ? e(route('customers.show', ['id' => $customer->id])) : e(route('customers.index')) ?>">Batal</a>
    </div>
</form>
