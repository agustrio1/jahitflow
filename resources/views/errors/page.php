<?php
/**
 * Halaman error penuh (non-HTMX).
 *
 * @var App\Core\View $view
 * @var int $status
 * @var string $title
 * @var string $message
 * @var string|null $debugTrace hanya ada saat APP_DEBUG=true
 */
$view->extend('layouts.app');
?>

<section class="jf-card jf-error-card">
    <p class="jf-error-code"><?= e($title) ?></p>
    <h2 class="jf-error-headline"><?= e($message) ?></h2>
    <p class="jf-text-muted">
        Periksa kembali alamat URL atau <a class="jf-link" href="<?= e(route('home')) ?>">kembali ke beranda</a>.
    </p>

    <?php if ($debugTrace !== null): ?>
        <details class="jf-debug">
            <summary>Detail exception (hanya tampil karena APP_DEBUG=true)</summary>
            <pre class="jf-debug-trace"><?= e($debugTrace) ?></pre>
        </details>
    <?php endif; ?>
</section>
