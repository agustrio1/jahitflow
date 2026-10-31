<?php

declare(strict_types=1);

namespace App\Console;

/**
 * Base untuk semua command CLI.
 *
 * Menyediakan akses ke Kernel (basePath + boot aplikasi untuk introspeksi
 * dan database) serta parsing argumen/opsi yang konsisten.
 */
abstract class BaseCommand implements Command
{
    public function __construct(protected Kernel $kernel)
    {
    }

    /** @param list<string> $args */
    abstract public function handle(array $args, ConsoleIO $io): int;

    /** @return list<string> argumen posisi (bukan --opsi) */
    protected function positional(array $args): array
    {
        return array_values(array_filter($args, static fn(string $a): bool => !str_starts_with($a, '--')));
    }

    protected function argument(array $args, int $index, ?string $default = null): ?string
    {
        $pos = $this->positional($args);
        return $pos[$index] ?? $default;
    }

    /** Deteksi flag seperti --force, --pretend. */
    protected function hasOption(array $args, string $name): bool
    {
        return in_array('--' . $name, $args, true);
    }

    /** Ambil nilai `--step=3`. */
    protected function optionValue(array $args, string $name, ?string $default = null): ?string
    {
        $prefix = '--' . $name . '=';
        foreach ($args as $arg) {
            if (str_starts_with($arg, $prefix)) {
                return substr($arg, strlen($prefix));
            }
        }
        return $default;
    }
}
