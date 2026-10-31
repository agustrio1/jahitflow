<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use App\Console\Support\MigrationRunner;
use App\Core\Database;
use PDOException;

/**
 * php bin/console migrate [--pretend]
 */
final class MigrateCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'migrate [--pretend]';
    }

    public function description(): string
    {
        return 'Jalankan semua migration yang belum diterapkan';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $pretend = $this->hasOption($args, 'pretend');

        try {
            $runner = $this->runner();
            $names = $runner->migrate($pretend);
        } catch (PDOException $e) {
            $io->error('Koneksi database gagal: ' . $e->getMessage());
            return 1;
        }

        if ($names === []) {
            $io->warn('Tidak ada migration yang perlu dijalankan (semua sudah diterapkan).');
            return 0;
        }

        foreach ($names as $name) {
            $io->line('  ' . ($pretend ? '[ Pretend ]' : '[ Migrate ]') . ' ' . $name);
        }

        $io->success($pretend
            ? count($names) . ' migration akan diterapkan (mode pretend, tidak ada perubahan).'
            : count($names) . ' migration diterapkan.');

        return 0;
    }

    private function runner(): MigrationRunner
    {
        $pdo = $this->kernel->app()->container()->make(Database::class)->pdo();
        return new MigrationRunner($pdo, $this->kernel->basePath('database/migrations'));
    }
}
