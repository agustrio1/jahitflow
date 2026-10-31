<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Grafik batang SVG MURNI (tanpa library — sesuai batasan DOC) untuk Laporan
 * §8.J. Gaya "stat bar" ala shadcn: label kiri, nilai kanan, satu batang yang
 * diskalakan terhadap nilai maksimum. Warna memakai token aplikasi yang sudah
 * ada (#0d9488 teal, #e2e5ea border, #6b7280 muted) — tanpa tone baru.
 */
final class Chart
{
    /**
     * @param list<array{label:string, value:float|int|string, display?:string}> $points
     *        'display' = nilai sudah terformat (mis. money/int) untuk label di kanan.
     */
    public static function bars(array $points, string $title = 'Grafik'): string
    {
        $rows = [];
        $max = 0.0;

        foreach ($points as $p) {
            $label = (string) ($p['label'] ?? '');
            $value = (float) ($p['value'] ?? 0);
            $display = (string) ($p['display'] ?? (string) $value);

            if ($label === '' && $display === '') {
                continue;
            }
            if ($value > $max) {
                $max = $value;
            }
            $rows[] = ['label' => $label, 'value' => $value, 'display' => $display];
        }

        if ($rows === []) {
            return '';
        }

        $width = 680;
        $groupH = 34;
        $padTop = 6;
        $barH = 8;
        $rx = 4;
        $height = $padTop * 2 + count($rows) * $groupH;

        $trackColor = '#e2e5ea';
        $barColor = '#0d9488';

        $out = [];
        $out[] = '<svg class="jf-chart" viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" height="' . $height . '"'
            . ' role="img" preserveAspectRatio="xMinYMin meet" aria-label="' . self::esc($title) . '">';
        $out[] = '<title>' . self::esc($title) . '</title>';

        foreach ($rows as $i => $r) {
            $y = $padTop + $i * $groupH;
            $textY = $y + 11;
            $barY = $y + 18;

            $ratio = $max > 0 ? ($r['value'] / $max) : 0;
            $barW = round($ratio * $width, 1);
            if ($r['value'] > 0 && $barW < 4) {
                $barW = 4; // nub kecil agar nilai > 0 tetap terlihat
            }

            $out[] = '<text x="0" y="' . $textY . '" class="jf-chart__label">' . self::esc($r['label']) . '</text>';
            $out[] = '<text x="' . $width . '" y="' . $textY . '" text-anchor="end" class="jf-chart__value">' . self::esc($r['display']) . '</text>';
            $out[] = '<rect x="0" y="' . $barY . '" width="' . $width . '" height="' . $barH . '" rx="' . $rx . '" fill="' . $trackColor . '"/>';
            if ($barW > 0) {
                $out[] = '<rect x="0" y="' . $barY . '" width="' . $barW . '" height="' . $barH . '" rx="' . $rx . '" fill="' . $barColor . '"/>';
            }
        }

        $out[] = '</svg>';

        return implode('', $out);
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
