<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;

/**
 * php bin/console route:list
 */
final class RouteListCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'route:list';
    }

    public function description(): string
    {
        return 'Tampilkan semua route terdaftar (method, path, nama, aksi, middleware)';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $routes = $this->kernel->app()->router()->all();

        if ($routes === []) {
            $io->warn('Belum ada route terdaftar.');
            return 0;
        }

        $io->title('Routes (' . count($routes) . ')');
        $rows = [];
        foreach ($routes as $r) {
            $rows[] = [
                sprintf('%-7s %-30s', $r['method'], $r['pattern']),
                $this->shorten($r['action']) . ($r['name'] ? '  [' . $r['name'] . ']' : ''),
            ];
        }

        $io->twoColumnTable($rows, 'METHOD / PATH', 'ACTION [name]');

        return 0;
    }

    private function shorten(string $action): string
    {
        $action = str_replace('App\\Http\\Controllers\\', '', $action);
        return str_replace('\\', '/', $action);
    }
}
