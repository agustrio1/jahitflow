<?php
/**
 * Fragment error untuk jawaban request HTMX — di-swap ke target,
 * tanpa layout penuh agar tidak merusak struktur halaman.
 *
 * @var int $status
 * @var string $title
 * @var string $message
 * @var string|null $debugTrace
 */
?>
<div class="jf-flash jf-flash--error" role="alert">
    <span class="jf-flash-text"><strong><?= e($title) ?>.</strong> <?= e($message) ?></span>
</div>
<?php if ($debugTrace !== null): ?>
    <pre class="jf-debug-trace"><?= e($debugTrace) ?></pre>
<?php endif; ?>
