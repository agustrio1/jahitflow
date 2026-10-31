<?php
/**
 * Command palette global (Ctrl+K / ⌘K) ala shadcn/ui — mencari MENU & item menu.
 *
 * Sumber data = config navigasi yang sama dengan sidebar, disaring per role dan
 * hanya item yang sudah 'built'. Filtrasi + navigasi keyboard memakai komponen
 * Alpine `globalSearch` (Alpine.js sudah dimuat di layout — TANPA dependensi baru).
 * Baris dirender di server agar bisa memakai helper icon() yang sama dengan sidebar.
 *
 * @var App\Models\User|null $authUser
 * @var array<int, array{label:string, items:array}> $navigation
 */

/** @var list<array{section:string, label:string, path:string, icon:string}> $flat */
$flat = [];
foreach (($navigation ?? []) as $section) {
    foreach ($section['items'] as $item) {
        $allowed = $item['roles'] === null || ($authUser !== null && $authUser->hasRole($item['roles']));
        if (!$allowed || empty($item['built'])) {
            continue; // jujur: hanya menu yang benar-benar bisa dituju
        }
        $flat[] = [
            'section' => (string) $section['label'],
            'label'   => (string) $item['label'],
            'path'    => (string) $item['path'],
            'icon'    => (string) ($item['icon'] ?? 'grid'),
        ];
    }
}
?>
<div class="jf-cmdk" x-data="globalSearch()"
     @keydown.escape.window="open = false"
     @keydown.window="(($event.metaKey || $event.ctrlKey) && $event.key && $event.key.toLowerCase() === 'k') && ($event.preventDefault(), toggle(!open))">

    <button type="button" class="jf-search-trigger" @click="toggle(true)" aria-label="Cari menu">
        <svg class="jf-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <span class="jf-search-trigger__text">Cari menu…</span>
        <kbd class="jf-kbd">Ctrl K</kbd>
    </button>

    <div class="jf-cmdk-overlay" x-show="open" x-cloak @click.self="open = false"
         role="dialog" aria-modal="true" aria-label="Pencarian menu">
        <div class="jf-cmdk-panel">
            <div class="jf-cmdk-search">
                <svg class="jf-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input class="jf-cmdk-input" type="text" x-ref="input" x-model="q" @input="filter()"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                       @keydown.enter.prevent="choose()"
                       placeholder="Ketik nama menu…" autocomplete="off" spellcheck="false"
                       aria-label="Ketik untuk mencari menu">
                <kbd class="jf-kbd">Esc</kbd>
            </div>

            <div class="jf-cmdk-list" id="jf-cmdk-list">
                <?php if ($flat === []): ?>
                    <p class="jf-cmdk-empty">Tidak ada menu tersedia untuk peran ini.</p>
                <?php else: ?>
                    <?php foreach ($flat as $it): ?>
                        <a class="jf-cmdk-item" href="<?= e($it['path']) ?>"
                           data-search="<?= e(mb_strtolower($it['label'] . ' ' . $it['section'])) ?>">
                            <?= icon($it['icon']) ?>
                            <span class="jf-cmdk-item__label"><?= e($it['label']) ?></span>
                            <span class="jf-cmdk-item__group"><?= e($it['section']) ?></span>
                        </a>
                    <?php endforeach; ?>
                    <p class="jf-cmdk-empty" x-show="shown === 0" x-cloak>Tidak ada menu yang cocok.</p>
                <?php endif; ?>
            </div>

            <div class="jf-cmdk-foot">
                <span><kbd class="jf-kbd">↑</kbd><kbd class="jf-kbd">↓</kbd> navigasi</span>
                <span><kbd class="jf-kbd">↵</kbd> buka</span>
                <span><kbd class="jf-kbd">esc</kbd> tutup</span>
            </div>
        </div>
    </div>
</div>

<script>
    // Daftarkan komponen SEBELUM Alpine dieksekusi (skrip inline berjalan saat
    // parsing body; Alpine dimuat dengan defer → alpine:init terjadi setelahnya).
    document.addEventListener('alpine:init', function () {
        Alpine.data('globalSearch', function () {
            return {
                open: false,
                q: '',
                shown: 0,

                rows: function () {
                    return Array.prototype.slice.call(document.querySelectorAll('#jf-cmdk-list .jf-cmdk-item'));
                },
                visible: function () {
                    return this.rows().filter(function (r) { return !r.classList.contains('is-hidden'); });
                },
                toggle: function (v) {
                    this.open = v;
                    if (v) {
                        var self = this;
                        this.$nextTick(function () {
                            self.q = '';
                            self.filter();
                            if (self.$refs.input) { self.$refs.input.focus(); }
                        });
                    }
                },
                filter: function () {
                    var term = this.q.trim().toLowerCase();
                    var rows = this.rows();
                    rows.forEach(function (r) {
                        var hit = term === '' || (r.getAttribute('data-search') || '').indexOf(term) !== -1;
                        r.classList.toggle('is-hidden', !hit);
                    });
                    rows.forEach(function (r) { r.classList.remove('is-active'); });
                    var vis = this.visible();
                    this.shown = vis.length;
                    if (vis.length > 0) { vis[0].classList.add('is-active'); }
                },
                move: function (dir) {
                    var vis = this.visible();
                    if (vis.length === 0) { return; }
                    var cur = vis.findIndex(function (r) { return r.classList.contains('is-active'); });
                    var next = cur < 0 ? 0 : (cur + dir + vis.length) % vis.length;
                    vis.forEach(function (r) { r.classList.remove('is-active'); });
                    vis[next].classList.add('is-active');
                    vis[next].scrollIntoView({ block: 'nearest' });
                },
                choose: function () {
                    var el = document.querySelector('#jf-cmdk-list .jf-cmdk-item.is-active');
                    if (el) { window.location.href = el.getAttribute('href'); }
                },
            };
        });
    });
</script>
