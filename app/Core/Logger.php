<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Logger file sederhana: storage/logs/app-YYYY-MM-DD.log.
 * Format satu baris + level, cukup untuk aplikasi berskala ini;
 * tidak perlu Monolog sampai terbukti dibutuhkan.
 */
final class Logger
{
    private const MAX_LINE_CONTEXT = 4096;

    public function __construct(private readonly string $path)
    {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0770, recursive: true);
        }
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context === [] ? '' : ' ' . $this->formatContext($context),
            PHP_EOL,
        );

        $file = $this->path . '/app-' . date('Y-m-d') . '.log';
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private function formatContext(array $context): string
    {
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($encoded === false || strlen($encoded) > self::MAX_LINE_CONTEXT) {
            $encoded = substr($encoded === false ? '{unencodable}' : $encoded, 0, self::MAX_LINE_CONTEXT) . '…';
        }
        return $encoded;
    }
}
