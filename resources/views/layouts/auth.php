<?php
/**
 * Layout halaman tamu (login) — kartu terpusat, tanpa sidebar.
 *
 * @var App\Core\View $view
 * @var string $title
 * @var string $csrfToken
 */
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <title><?= e($title ?? 'Masuk — JahitFlow') ?></title>

    <link rel="icon" type="image/svg+xml" href="/assets/img/logo-mark.svg">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <script src="https://unpkg.com/htmx.org@2.0.8" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.15.12/dist/cdn.min.js" defer></script>
</head>
<body class="jf-body jf-auth-body">
    <div class="jf-auth-wrap">
        <div class="jf-auth-brand">
            <img src="/assets/img/logo.svg" alt="JahitFlow" class="jf-logo" height="34">
        </div>

        <div class="jf-auth-card jf-card">
            <div id="flash-area"><?= $view->include('partials.flash') ?></div>
            <?= $view->yieldSection('content') ?>
        </div>

        <p class="jf-auth-foot">JahitFlow — Sistem Operasional Konveksi</p>
    </div>
</body>
</html>
