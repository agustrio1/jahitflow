<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use RuntimeException;

/**
 * help <command> — signature, deskripsi, dan contoh pemakaian.
 */
final class HelpCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'help {command}';
    }

    public function description(): string
    {
        return 'Tampilkan bantuan untuk satu command';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $name = $this->argument($args, 0);

        if ($name === null) {
            throw new RuntimeException('Sebutkan command. Contoh: php bin/console help list');
        }

        $commands = $this->kernel->commands();

        if (!isset($commands[$name])) {
            throw new RuntimeException("Command [{$name}] tidak dikenal. Lihat `php bin/console list`.");
        }

        $command = new $commands[$name]($this->kernel);

        $io->title('help: ' . $command->signature());
        $io->line($command->description());
        $io->line('');
        $io->line('  Pemakaian : php bin/console ' . $command->signature());

        return 0;
    }
}
