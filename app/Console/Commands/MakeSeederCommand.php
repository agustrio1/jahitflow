<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use App\Console\Support\ClassGenerator;
use RuntimeException;

/**
 * make:seeder DemoSeeder → database/seeders/DemoSeeder.php
 */
final class MakeSeederCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'make:seeder {name}';
    }

    public function description(): string
    {
        return 'Buat class Seeder baru';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $raw = $this->argument($args, 0);
        if ($raw === null || $raw === '') {
            throw new RuntimeException('Sebutkan nama seeder. Contoh: php bin/console make:seeder DemoSeeder');
        }

        if (str_contains($raw, '..') || !preg_match('#^[A-Za-z][A-Za-z0-9_]*$#', $raw)) {
            throw new RuntimeException("Nama [{$raw}] tidak valid (huruf/angka/underscore, diawali huruf).");
        }

        $class = str_replace(' ', '', ucwords(str_replace('_', ' ', $raw)));
        if (!str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $relative = 'database/seeders/' . $class . '.php';

        $generator = new ClassGenerator($this->kernel->basePath(), $this->kernel->basePath('app/Console/Stubs'));
        $contents = $generator->render('seeder', ['class' => $class]);
        $generator->write($relative, $contents, $this->hasOption($args, 'force'));

        $io->success("Seeder {$class}  →  {$relative}");

        return 0;
    }
}
