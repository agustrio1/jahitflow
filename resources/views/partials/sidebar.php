<?php
/**
 * Sidebar navigasi. Menyaring item berdasarkan role user aktif, dan
 * menandai modul yang belum dibangun sebagai nonaktif (jujur, tanpa 404).
 *
 * @var App\Core\View $view
 * @var App\Models\User|null $authUser
 * @var array<int, array{label:string, items:array}> $navigation
 * @var string $currentPath
 */
?>
<div class="jf-sidebar-head">
    <img src="/assets/img/logo.svg" alt="JahitFlow" class="jf-logo">
    <button class="jf-icon-btn jf-sidebar-close" @click="sidebarOpen = false" aria-label="Tutup menu">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
    </button>
</div>

<nav class="jf-nav" aria-label="Navigasi utama">
    <?php foreach ($navigation as $section): ?>
        <?php
        // Kumpulkan item yang boleh dilihat role ini sebelum merender label,
        // agar bagian kosong tidak menampilkan judul yatim.
        $visible = [];
        foreach ($section['items'] as $item) {
            $allowed = $item['roles'] === null || ($authUser !== null && $authUser->hasRole($item['roles']));
            if ($allowed) {
                $visible[] = $item;
            }
        }
        if ($visible === []) {
            continue;
        }
        ?>
        <div class="jf-nav-section">
            <p class="jf-nav-label"><?= e($section['label']) ?></p>
            <?php foreach ($visible as $item): ?>
                <?php $active = ($currentPath ?? '') === $item['path']; ?>
                <?php if (!empty($item['built'])): ?>
                    <a class="jf-nav-item<?= $active ? ' is-active' : '' ?>" href="<?= e($item['path']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                        <?= icon($item['icon'] ?? 'grid') ?>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php else: ?>
                    <span class="jf-nav-item is-disabled" aria-disabled="true" title="Modul ini belum tersedia">
                        <?= icon($item['icon'] ?? 'grid') ?>
                        <span><?= e($item['label']) ?></span>
                        <em class="jf-nav-soon">segera</em>
                    </span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</nav>
