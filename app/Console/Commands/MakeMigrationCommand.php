<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use App\Console\Support\ClassGenerator;
use RuntimeException;

/**
 * make:migration create_sales_orders_table
 * → database/migrations/2026_10_28_143000_create_sales_orders_table.php
 *
 * Nama class diturunkan dari slug (StudlyCase) dan harus cocok dengan
 * konvensi yang dipakai MigrationRunner untuk memetakan file → class.
 */
final class MakeMigrationCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'make:migration {name}';
    }

    public function description(): string
    {
        return 'Buat file migration baru (dengan prefix waktu)';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $raw = $this->argument($args, 0);
        if ($raw === null || $raw === '') {
            throw new RuntimeException('Sebutkan nama migration. Contoh: php bin/console make:migration create_customers_table');
        }

        $snake = $this->snake($raw);
        if ($snake === '') {
            throw new RuntimeException("Nama [{$raw}] tidak dapat dijadikan nama migration.");
        }

        $class = $this->studly($snake);
        $filename = date('Y_m_d_His') . '_' . $snake . '.php';
        $relative = 'database/migrations/' . $filename;

        $generator = new ClassGenerator($this->kernel->basePath(), $this->kernel->basePath('app/Console/Stubs'));
        $contents = $generator->render('migration', ['class' => $class]);
        $generator->write($relative, $contents, $this->hasOption($args, 'force'));

        $io->success("Migration {$class}  →  {$relative}");

        return 0;
    }

    private function snake(string $value): string
    {
        $value = str_replace('\\', '/', $value);
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        return trim($value, '_');
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
    }
}
