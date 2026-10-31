<?php

declare(strict_types=1);

namespace App\Console;

/**
 * Output CLI konsisten: tulis, sukses, gagal, tabel —
 * tanpa warna ANSI berlebihan, aman di PowerShell maupun terminal Linux.
 */
final class ConsoleIO
{
    /** @param resource $out */
    public function __construct(private $out = STDOUT)
    {
    }

    public function line(string $text = ''): void
    {
        fwrite($this->out, $text . PHP_EOL);
    }

    public function title(string $text): void
    {
        $this->line('');
        $this->line($text);
        $this->line(str_repeat('=', max(strlen($text), 4)));
    }

    public function success(string $text): void
    {
        $this->line('[OK] ' . $text);
    }

    public function error(string $text): void
    {
        fwrite(STDERR, '[ERR] ' . $text . PHP_EOL);
    }

    public function warn(string $text): void
    {
        $this->line('[!] ' . $text);
    }

    /**
     * Tabel dua kolom sederhana untuk `list`, `migrate:status`, `route:list`.
     *
     * @param list<array{0: string, 1: string}> $rows
     */
    public function twoColumnTable(array $rows, string $col1 = '', string $col2 = ''): void
    {
        $width1 = strlen($col1);
        foreach ($rows as $row) {
            $width1 = max($width1, strlen($row[0]));
        }

        if ($col1 !== '') {
            $this->line(sprintf("  %-{$width1}s  %s", $col1, $col2));
            $this->line('  ' . str_repeat('-', $width1 + 2 + strlen($col2)));
        }

        foreach ($rows as $row) {
            $this->line(sprintf("  %-{$width1}s  %s", $row[0], $row[1]));
        }
    }

    /** Konfirmasi interaktif untuk command berbahaya (rollback, seed destruktif). */
    public function confirm(string $question): bool
    {
        $this->line($question . ' [y/N] ');
        $answer = strtolower(trim((string) fgets(STDIN)));
        return $answer === 'y' || $answer === 'yes';
    }
}
