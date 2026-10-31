<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;

/**
 * php bin/console test [--filter=...] [argumen phpunit lain]
 *
 * Wrapper NYATA ke PHPUnit: meneruskan seluruh output dan exit code asli.
 * Tidak pernah mencetak "lulus" tanpa menjalankan phpunit (aturan §5 & §13).
 * Dijalankan oleh developer, bukan oleh agen.
 */
final class TestCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'test [argumen-phpunit...]';
    }

    public function description(): string
    {
        return 'Jalankan suite PHPUnit (vendor/phpunit/phpunit/phpunit)';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $phpunit = $this->kernel->basePath('vendor/phpunit/phpunit/phpunit');

        if (!is_file($phpunit)) {
            $io->error('PHPUnit belum terpasang. Jalankan `composer install` terlebih dahulu.');
            return 1;
        }

        $cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpunit);
        foreach ($args as $arg) {
            $cli .= ' ' . escapeshellarg($arg);
        }

        $io->line('Menjalankan: ' . $cli);
        $io->line('');

        $exit = 0;
        passthru($cli, $exit);

        return $exit;
    }
}
