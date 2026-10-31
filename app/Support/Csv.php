<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pembentuk berkas CSV laporan (§8.J: "Ekspor CSV bila diperlukan"). MURNI —
 * tanpa DB/view. Memakai pemisah ';' agar ramah Excel locale Indonesia dan
 * menulis nilai MENTAH (angka/teks apa adanya), bukan tampilan ber-format —
 * supaya file dapat diolah ulang, bukan hanya dibaca.
 */
final class Csv
{
    private const DELIMITER = ';';

    /**
     * @param string $title       judul laporan (baris header)
     * @param array{from: string, to: string} $period
     * @param list<array{caption?: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}> $sections
     */
    public static function build(string $title, array $period, array $sections): string
    {
        $lead = [
            ['Laporan', $title],
            ['Periode', (string) ($period['from'] ?? ''), (string) ($period['to'] ?? '')],
            ['Diekspor', date('Y-m-d H:i:s')],
        ];

        return self::assemble($lead, $sections);
    }

    /**
     * Ekspor SATU dokumen (mis. Sales Order / Production Order): baris meta
     * label→nilai, lalu satu tabel ber-caption. Nilai tetap ditulis MENTAH
     * (angka/tanpa format mata uang) supaya file dapat diolah ulang.
     *
     * @param array<string,string|int|float|null> $meta
     * @param list<array{key: string, label: string}> $columns
     * @param list<array<string, mixed>> $rows
     */
    public static function document(array $meta, string $caption, array $columns, array $rows): string
    {
        $lead = [];
        foreach ($meta as $label => $value) {
            $lead[] = [(string) $label, self::scalar($value)];
        }
        $lead[] = ['Diekspor', date('Y-m-d H:i:s')];

        return self::assemble($lead, [[
            'caption' => $caption,
            'columns' => $columns,
            'rows'    => $rows,
        ]]);
    }

    /**
     * Inti pembentukan: tulis baris meta lalu tiap bagian (caption + kolom + baris).
     *
     * @param list<list<string>> $leadRows
     * @param list<array{caption?: string, columns: list<array{key: string, label: string}>, rows: list<array<string, mixed>>}> $sections
     */
    private static function assemble(array $leadRows, array $sections): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        foreach ($leadRows as $fields) {
            self::row($stream, $fields);
        }

        foreach ($sections as $section) {
            self::row($stream, []); // baris kosong pemisah bagian
            $caption = (string) ($section['caption'] ?? 'Data');
            self::row($stream, [$caption]);

            $columns = $section['columns'] ?? [];
            self::row($stream, array_map(static fn(array $c): string => (string) ($c['label'] ?? ''), $columns));

            foreach ($section['rows'] ?? [] as $row) {
                self::row($stream, array_map(
                    static fn(array $c): string => self::scalar($row[$c['key'] ?? ''] ?? ''),
                    $columns,
                ));
            }

            if (($section['rows'] ?? []) === []) {
                self::row($stream, ['(tidak ada data)']);
            }
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }

    /** Ubah nilai sel menjadi string aman untuk CSV (tanpa format mata uang). */
    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * @param resource $stream
     * @param list<string> $fields
     */
    private static function row($stream, array $fields): void
    {
        fputcsv($stream, $fields, self::DELIMITER, '"', '\\');
    }
}
