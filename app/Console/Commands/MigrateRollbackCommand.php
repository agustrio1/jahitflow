<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use App\Console\Support\MigrationRunner;
use App\Core\Database;
use PDOException;

/**
 * php bin/console migrate:rollback [--step=N] [--force]
 *
 * PERINGATAN: rollback memanggil down() yang dapat bersifat destruktif
 * (DROP TABLE/COLUMN). Karena itu wajib konfirmasi interaktif, kecuali
 * `--force` diberikan secara eksplisit (aturan §5 & §7 DOC).
 */
final class MigrateRollbackCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'migrate:rollback [--step=N] [--force]';
    }

    public function description(): string
    {
        return 'Rollback batch terakhir (atau --step N migration terbaru) dengan konfirmasi';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $step = $this->optionValue($args, 'step');
        $step = $step !== null ? (int) $step : null;

        $io->warn('Rollback dapat menjatuhkan tabel/kolom dan menghapus data. Langkah ini tidak dapat dibatalkan otomatis.');

        if (!$this->hasOption($args, 'force') && !$io->confirm('Lanjutkan rollback?')) {
            $io->line('Dibatalkan.');
            return 0;
        }

        try {
            $rolled = $this->runner()->rollback($step);
        } catch (PDOException $e) {
            $io->error('Koneksi database gagal: ' . $e->getMessage());
            return 1;
        }

        if ($rolled === []) {
            $io->warn('Tidak ada migration untuk di-rollback.');
            return 0;
        }

        foreach ($rolled as $name) {
            $io->line('  [ Rollback ] ' . $name);
        }
        $io->success(count($rolled) . ' migration di-rollback.');

        return 0;
    }

    private function runner(): MigrationRunner
    {
        $pdo = $this->kernel->app()->container()->make(Database::class)->pdo();
        return new MigrationRunner($pdo, $this->kernel->basePath('database/migrations'));
    }
}
