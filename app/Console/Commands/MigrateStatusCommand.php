<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use App\Console\Support\MigrationRunner;
use App\Core\Database;
use PDOException;

/**
 * php bin/console migrate:status
 */
final class MigrateStatusCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'migrate:status';
    }

    public function description(): string
    {
        return 'Tampilkan status setiap migration (diterapkan / menunggu)';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        try {
            $rows = $this->runner()->status();
        } catch (PDOException $e) {
            $io->error('Koneksi database gagal: ' . $e->getMessage());
            return 1;
        }

        if ($rows === []) {
            $io->warn('Belum ada file migration di database/migrations.');
            return 0;
        }

        $io->title('Migration status');
        $table = [];
        $pending = 0;
        foreach ($rows as $row) {
            $pending += $row['applied'] ? 0 : 1;
            $table[] = [
                $row['name'],
                $row['applied'] ? 'Batch ' . $row['batch'] : '(menunggu)',
            ];
        }

        $io->twoColumnTable($table, 'Migration', 'Status');
        $io->line('');
        $io->line($pending === 0 ? 'Semua migration sudah diterapkan.' : "{$pending} migration menunggu untuk dijalankan.");

        return 0;
    }

    private function runner(): MigrationRunner
    {
        $pdo = $this->kernel->app()->container()->make(Database::class)->pdo();
        return new MigrationRunner($pdo, $this->kernel->basePath('database/migrations'));
    }
}
