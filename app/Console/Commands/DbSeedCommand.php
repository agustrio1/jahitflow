<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use App\Console\Support\SeederRunner;
use App\Core\Database;
use PDOException;
use RuntimeException;

/**
 * php bin/console db:seed [--class=DemoSeeder]
 */
final class DbSeedCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'db:seed [--class=Name]';
    }

    public function description(): string
    {
        return 'Jalankan seeder data demo (semua, atau satu via --class)';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $only = $this->optionValue($args, 'class');

        try {
            $ran = $this->runner()->seed($only !== null ? $this->fileFromName((string) $only) : null);
        } catch (PDOException $e) {
            $io->error('Koneksi database gagal: ' . $e->getMessage());
            return 1;
        }

        if ($ran === []) {
            $io->warn('Tidak ada seeder di database/seeders.');
            return 0;
        }

        foreach ($ran as $name) {
            $io->line('  [ Seed ] ' . $name);
        }
        $io->success('Seeder selesai: ' . implode(', ', $ran) . '.');

        return 0;
    }

    /** "DemoSeeder" / "demo_seeder" → "DemoSeeder" (basename file). */
    private function fileFromName(string $name): string
    {
        if (!preg_match('#^[A-Za-z][A-Za-z0-9_]*$#', $name)) {
            throw new RuntimeException("Nama seeder [{$name}] tidak valid.");
        }
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    private function runner(): SeederRunner
    {
        $pdo = $this->kernel->app()->container()->make(Database::class)->pdo();
        return new SeederRunner($pdo, $this->kernel->basePath('database/seeders'));
    }
}
