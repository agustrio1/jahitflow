<?php
/**
 * Halaman verifikasi Core Foundation (Fase 1).
 * Akan digantikan dashboard sesungguhnya pada Fase 4.
 *
 * @var App\Core\View $view
 * @var list<array{label: string, ok: bool, note: string}> $checks
 */
$view->extend('layouts.app');
?>

<p class="jf-text-muted">
    Inti framework PHP Native sudah terpasang. Halaman ini menguji rantai
    request → router → middleware CSRF → controller → view secara nyata,
    bukan klaim. Gunakan form di bawah untuk menguji flash + redirect.
</p>

<section class="jf-card">
    <h2 class="jf-card-title">Status Komponen</h2>
    <table class="jf-table">
        <thead>
            <tr>
                <th>Komponen</th>
                <th>Status</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checks as $check): ?>
                <tr>
                    <td><?= e($check['label']) ?></td>
                    <td>
                        <span class="jf-badge jf-badge--<?= $check['ok'] ? 'success' : 'danger' ?>">
                            <?= $check['ok'] ? 'OK' : 'BELUM' ?>
                        </span>
                    </td>
                    <td class="jf-text-muted"><?= e($check['note']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="jf-card">
    <h2 class="jf-card-title">Uji Flash + CSRF</h2>
    <p class="jf-text-muted">
        Form POST ini melewati CsrfMiddleware. Jika token hilang/basa,
        aplikasi harus merespons dengan halaman 419 — itu tanda proteksi bekerja.
    </p>
    <form method="post" action="<?= e(route('foundation.flashDemo')) ?>" class="jf-form">
        <?= csrf_field() ?>
        <div class="jf-field">
            <label class="jf-label" for="nama">Nama (opsional)</label>
            <input class="jf-input" type="text" id="nama" name="nama"
                   value="<?= e($oldInput['nama'] ?? '') ?>" placeholder="mis. Bu Sari">
        </div>
        <button type="submit" class="jf-btn jf-btn--primary">Kirim flash message</button>
    </form>
</section>
