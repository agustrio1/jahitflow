<?php
/**
 * Topbar: pemicu menu mobile, penanda lingkungan, dan menu user
 * (dropdown Alpine) termasuk tombol logout via POST ber-CSRF.
 *
 * @var App\Core\View $view
 * @var App\Models\User|null $authUser
 * @var string $appEnv
 */
?>
<div class="jf-topbar-left">
    <button class="jf-icon-btn jf-menu-toggle" @click="sidebarOpen = !sidebarOpen" aria-label="Buka menu navigasi">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    <?= $view->include('partials.global-search') ?>
</div>

<div class="jf-topbar-right" x-data="{ open: false }">
    <span class="jf-env-badge"><?= e($appEnv ?? 'local') ?></span>

    <div class="jf-user">
        <button class="jf-user-btn" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
            <span class="jf-avatar"><?= e($authUser?->initials() ?? '?') ?></span>
            <span class="jf-user-meta">
                <span class="jf-user-name"><?= e($authUser?->name ?? 'Tamu') ?></span>
                <span class="jf-user-role"><?= e($authUser?->roleLabel() ?? '') ?></span>
            </span>
        </button>

        <div class="jf-user-menu" x-show="open" @click.outside="open = false" x-cloak>
            <p class="jf-user-email"><?= e($authUser?->email ?? '') ?></p>
            <form method="post" action="<?= e(route('logout')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="jf-user-logout">
                    <?= icon('logout') ?><span>Keluar</span>
                </button>
            </form>
        </div>
    </div>
</div>
