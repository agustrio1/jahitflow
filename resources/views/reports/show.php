<?php

use App\Support\Chart;

/**
 * Renderer generik satu laporan (§8.J). Bentuk data datang dari ReportService
 * sebagai deskriptor (KPI + tabel bertipe), sehingga satu view melayani enam
 * laporan. Nilai disimpan MENTAH di baris; format tampilan ditentukan di sini
 * lewat tipe kolom — dan CSV memakai nilai mentah yang sama.
 *
 * @var App\Core\View $view
 * @var array<string, mixed> $report
 * @var string $from
 * @var string $to
 */
$view->extend('layouts.app');

$key = (string) $report['key'];
$baseUrl = route('reports.show', ['key' => $key]);
$csvUrl = $baseUrl . '?' . http_build_query(['export' => 'csv', 'dari' => $from, 'sampai' => $to]);

// Format sel berdasarkan tipe kolom (nilai mentah → tampilan).
$fmt = static function (mixed $v, string $type): string {
    return match ($type) {
        'money' => money($v),
        'int' => number_format((int) $v, 0, ',', '.'),
        'num' => rtrim(rtrim(number_format((float) $v, 3, ',', '.'), '0'), ','),
        'date' => tanggal(is_string($v) ? $v : null),
        default => (string) ($v ?? ''),
    };
};

// Grafik opsional per bagian: bar SVG murni (Chart::bars) — dilewati bila
// section tidak mendeklarasikan 'chart'. Konsumsi {label,value,type}.
$sectionChart = static function (array $section) use ($fmt): string {
    $cfg = $section['chart'] ?? null;
    if (!is_array($cfg)) {
        return '';
    }

    $labelKey = (string) ($cfg['label'] ?? '');
    $valueKey = (string) ($cfg['value'] ?? '');
    $type = (string) ($cfg['type'] ?? 'int');

    $points = [];
    foreach ($section['rows'] ?? [] as $row) {
        $points[] = [
            'label'   => (string) ($row[$labelKey] ?? ''),
            'value'   => (float) ($row[$valueKey] ?? 0),
            'display' => $fmt($row[$valueKey] ?? '', $type),
        ];
    }

    return Chart::bars($points, (string) ($section['caption'] ?? 'Grafik'));
};
?>
<div class="jf-report-bar">
    <form class="jf-filters" method="get" action="<?= e($baseUrl) ?>">
        <div class="jf-field-inline">
            <label class="jf-label" for="dari">Dari</label>
            <input class="jf-input" type="date" id="dari" name="dari" value="<?= e($from) ?>">
        </div>
        <div class="jf-field-inline">
            <label class="jf-label" for="sampai">Sampai</label>
            <input class="jf-input" type="date" id="sampai" name="sampai" value="<?= e($to) ?>">
        </div>
        <button type="submit" class="jf-btn jf-btn--sm">Terapkan</button>
    </form>
    <span class="jf-report-bar__spacer"></span>
    <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e($csvUrl) ?>">Unduh CSV</a>
</div>

<?php if (!empty($report['kpis'])): ?>
    <div class="jf-kpis">
        <?php foreach ($report['kpis'] as $kpi): ?>
            <div class="jf-kpi">
                <div class="jf-kpi__label"><?= e($kpi['label']) ?></div>
                <div class="jf-kpi__value"><?= e($kpi['value']) ?></div>
                <?php if (!empty($kpi['hint'])): ?>
                    <div class="jf-kpi__hint"><?= e($kpi['hint']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p class="jf-text-muted jf-sm">
    Periode: <strong><?= e(tanggal($from)) ?></strong> s.d. <strong><?= e(tanggal($to)) ?></strong>
    (<?= (int) $report['period']['days'] ?> hari<?= !empty($report['period']['isDefault']) ? ' — default 30 hari terakhir' : '' ?>).
</p>

<?php foreach ($report['sections'] as $section): ?>
    <section class="jf-card">
        <h2 class="jf-card-title"><?= e($section['caption']) ?></h2>
        <?php if (($section['rows'] ?? []) === []): ?>
            <div class="jf-empty">
                <p class="jf-empty-text"><?= e($section['empty'] ?? 'Tidak ada data pada periode ini.') ?></p>
            </div>
        <?php else: ?>
            <?php if (!empty($section['chart'])): ?>
                <div class="jf-chart-wrap"><?= $sectionChart($section) ?></div>
            <?php endif; ?>
            <div class="jf-table-wrap">
                <table class="jf-table">
                    <thead>
                        <tr>
                            <?php foreach ($section['columns'] as $col): ?>
                                <th scope="col" class="<?= ($col['align'] ?? '') === 'right' ? 'jf-text-right' : '' ?>">
                                    <?= e($col['label']) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($section['columns'] as $col): ?>
                                    <?php $type = (string) ($col['type'] ?? 'text'); ?>
                                    <td class="<?= ($col['align'] ?? '') === 'right' ? 'jf-text-right' : '' ?><?= in_array($type, ['money', 'int', 'num'], true) ? ' jf-mono' : '' ?>">
                                        <?= e($fmt($row[$col['key']] ?? '', $type)) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php if (!empty($report['note'])): ?>
    <p class="jf-hint"><?= e($report['note']) ?></p>
<?php endif; ?>

<div class="jf-form-actions">
    <a class="jf-btn jf-btn--ghost" href="<?= e(route('reports.index')) ?>">← Semua laporan</a>
</div>
