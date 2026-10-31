<?php
/**
 * Form masuk. Extended dari layout auth. Error validasi & throttle
 * muncul dari session (flashErrors) yang sudah dibagikan sebagai
 * $fieldErrors; nilai email dipertahankan via $oldInput.
 *
 * @var App\Core\View $view
 * @var array<string, list<string>> $fieldErrors
 * @var array<string, mixed> $oldInput
 */
$view->extend('layouts.auth');

$emailErr = $fieldErrors['email'][0] ?? null;
$passErr = $fieldErrors['password'][0] ?? null;
?>
<h1 class="jf-auth-title">Masuk</h1>
<p class="jf-auth-sub">Gunakan akun operasional Anda untuk melanjutkan.</p>

<form method="post" action="<?= e(route('login.attempt')) ?>" class="jf-form jf-auth-form" novalidate>
    <?= csrf_field() ?>

    <div class="jf-field">
        <label class="jf-label" for="email">Email</label>
        <input class="jf-input<?= $emailErr ? ' is-invalid' : '' ?>" type="email" id="email" name="email"
               value="<?= e($oldInput['email'] ?? '') ?>" autocomplete="username" required>
        <?php if ($emailErr): ?><p class="jf-field-error"><?= e($emailErr) ?></p><?php endif; ?>
    </div>

    <div class="jf-field">
        <label class="jf-label" for="password">Password</label>
        <input class="jf-input<?= $passErr ? ' is-invalid' : '' ?>" type="password" id="password" name="password"
               autocomplete="current-password" required>
        <?php if ($passErr): ?><p class="jf-field-error"><?= e($passErr) ?></p><?php endif; ?>
    </div>

    <button type="submit" class="jf-btn jf-btn--primary jf-btn--block">Masuk</button>
</form>

<div class="jf-auth-hint">
    <p class="jf-text-muted"><strong>Akun demo</strong> (password: <code>Password123!</code>):</p>
    <ul>
        <li>admin@jahitflow.test — Administrator</li>
        <li>manager@jahitflow.test — Manajer</li>
        <li>operator@jahitflow.test — Operator Produksi</li>
        <li>keuangan@jahitflow.test — Keuangan</li>
    </ul>
</div>
