<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\ConsoleIO;

final class MakeCommandCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:command {name}';
    }

    public function description(): string
    {
        return 'Buat class Command CLI baru';
    }

    protected function stubName(): string
    {
        return 'command';
    }

    protected function namespace(): string
    {
        return 'App\\Console\\Commands';
    }

    protected function subDirectory(): string
    {
        return 'app/Console/Commands';
    }

    protected function classSuffix(): string
    {
        return 'Command';
    }

    /** Turunkan nama command dari class: SyncStockCommand -> sync:stock. */
    protected function vars(string $class, array $extra = []): array
    {
        $base = preg_replace('/Command$/', '', $class) ?? $class;
        $words = preg_split('/(?<!^)[A-Z]/', $base) ?: [$base];
        $commandName = strtolower(implode(':', $words));

        return array_merge($extra, ['commandName' => $commandName]);
    }

    protected function afterWrite(string $class, ConsoleIO $io): void
    {
        $io->warn("Daftarkan command di app/Console/Kernel.php (\$commands) agar muncul di `list` dan bisa dijalankan.");
    }
}
