<?php
/**
 * Layout utama terautentikasi: sidebar + topbar + area konten.
 *
 * @var App\Core\View $view
 * @var string $title
 * @var string $csrfToken
 * @var string $appEnv
 */
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrfToken ?? '') ?>">
    <title><?= e($title ?? 'JahitFlow') ?></title>

    <link rel="icon" type="image/svg+xml" href="/assets/img/logo-mark.svg">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">

    <!-- Library frontend dipatok eksplisit (aturan §3 DOC) -->
    <script src="https://unpkg.com/htmx.org@2.0.8" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.15.12/dist/cdn.min.js" defer></script>
    <script>
        // Kirim token CSRF pada setiap request HTMX (header X-CSRF-Token).
        document.addEventListener('DOMContentLoaded', function () {
            document.body.addEventListener('htmx:configRequest', function (evt) {
                var meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) { evt.detail.headers['X-CSRF-Token'] = meta.content; }
            });
        });
    </script>
</head>
<body class="jf-body jf-app-body" x-data="{ sidebarOpen: false }">
    <a class="jf-skip" href="#jf-content">Lewati ke konten utama</a>

    <div class="jf-app-shell">
        <aside class="jf-sidebar" :class="{ 'is-open': sidebarOpen }" id="jf-sidebar">
            <?= $view->include('partials.sidebar') ?>
        </aside>

        <div class="jf-app-col">
            <header class="jf-topbar">
                <?= $view->include('partials.topbar') ?>
            </header>

            <main class="jf-main" id="jf-content">
                <div class="jf-container">
                    <div class="jf-page-head">
                        <h1 class="jf-page-title"><?= e($title ?? '') ?></h1>
                        <?php if (!empty($pageSubtitle ?? '')): ?>
                            <p class="jf-page-sub"><?= e($pageSubtitle ?? '') ?></p>
                        <?php endif; ?>
                    </div>

                    <div id="flash-area"><?= $view->include('partials.flash') ?></div>

                    <?= $view->yieldSection('content') ?>
                </div>

                <footer class="jf-footer">
                    <div class="jf-container">
                        <span>JahitFlow &copy; <?= date('Y') ?> — Sistem Operasional Konveksi</span>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <!-- Layar overlay untuk sidebar di layar kecil -->
    <div class="jf-backdrop" x-show="sidebarOpen" @click="sidebarOpen = false" x-cloak></div>
</body>
</html>
