<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;

/**
 * Menampilkan command yang BENAR-BENAR tersedia saat ini.
 */
final class ListCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'list';
    }

    public function description(): string
    {
        return 'Tampilkan daftar command CLI yang tersedia';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $io->title('JahitFlow Console');
        $io->line('Pemakaian: php bin/console <command> [argumen]');
        $io->line('');

        $rows = [];
        foreach ($this->kernel->commands() as $name => $class) {
            $command = new $class($this->kernel);
            $rows[] = [$name, $command->description()];
        }

        $io->twoColumnTable($rows, 'Command', 'Deskripsi');
        $io->line('');
        $io->line('Jalankan `php bin/console help <command>` untuk detail.');

        return 0;
    }
}
