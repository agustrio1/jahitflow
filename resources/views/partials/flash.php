<?php
/**
 * Flash message — partial, dipakai ulang oleh layout dan jawaban HTMX.
 * @var list<array{type: string, message: string}> $flashMessages
 */
?>
<?php foreach ($flashMessages ?? [] as $flash): ?>
    <div class="jf-flash jf-flash--<?= e($flash['type']) ?>" role="status">
        <span class="jf-flash-text"><?= e($flash['message']) ?></span>
        <button type="button" class="jf-flash-close" aria-label="Tutup"
                onclick="this.parentElement.remove()">×</button>
    </div>
<?php endforeach; ?>
