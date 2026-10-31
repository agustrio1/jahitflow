<?php

use App\Support\Paginator;

/**
 * Pagination reusabel. Link dibuat sebagai query string penuh agar
 * bekerja tanpa HTMX; saat HTMX aktif, hx-get menukar target yang
 * diberikan (mis. #customers-table) sehingga filter & halaman ikut.
 *
 * @var Paginator $paginator
 * @var string    $baseUrl    URL dasar (route index)
 * @var array<string, mixed> $query  Filter aktif tanpa 'page'
 * @var string|null $hxTarget Selector target HTMX (null = link biasa)
 */
$mkLink = static function (int $page) use ($baseUrl, $query): string {
    $params = array_filter($query, static fn($v): bool => $v !== '' && $v !== 0 && $v !== null);
    $params['page'] = $page;

    return $baseUrl . '?' . http_build_query($params);
};

$hx = static fn(string $sel): string => $sel !== '' ? ' hx-get="' . e($sel) . '" hx-target="' . e($sel) . '" hx-swap="innerHTML scroll:top"' : '';
$target = $hxTarget ?? '';
?>
<?php if ($paginator->total > 0): ?>
<nav class="jf-pagination" aria-label="Navigasi halaman">
    <span class="jf-pagination-info">
        Menampilkan <?= e((string) $paginator->from()) ?>–<?= e((string) $paginator->to()) ?>
        dari <?= e((string) $paginator->total) ?>
    </span>
    <span class="jf-pagination-links">
        <?php if ($paginator->hasPrev()): ?>
            <a class="jf-btn jf-btn--sm<?= $target !== '' ? ' jf-btn--ghost' : '' ?>"
               href="<?= e($mkLink($paginator->page - 1)) ?>"<?= $hx($target) ?>>Sebelumnya</a>
        <?php endif; ?>

        <span class="jf-pagination-page">Hal <?= e((string) $paginator->page) ?> / <?= e((string) $paginator->lastPage()) ?></span>

        <?php if ($paginator->hasNext()): ?>
            <a class="jf-btn jf-btn--sm<?= $target !== '' ? ' jf-btn--ghost' : '' ?>"
               href="<?= e($mkLink($paginator->page + 1)) ?>"<?= $hx($target) ?>>Berikutnya</a>
        <?php endif; ?>
    </span>
</nav>
<?php endif; ?>
