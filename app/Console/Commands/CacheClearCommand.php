<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;

/**
 * php bin/console cache:clear
 *
 * Mengosongkan storage/cache (bukan logs/uploads). Pada Fase 1–2 belum ada
 * cache aplikasi, jadi command ini menyiapkan infrastruktur yang jujur:
 * benar-benar menghapus file, bukan sekadar mencetak sukses.
 */
final class CacheClearCommand extends BaseCommand
{
    public function signature(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Kosongkan folder storage/cache';
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $dir = $this->kernel->basePath('storage/cache');

        if (!is_dir($dir)) {
            $io->warn('Folder cache tidak ada; tidak ada yang dibersihkan.');
            return 0;
        }

        $removed = 0;
        foreach (glob($dir . '/*') ?: [] as $item) {
            if (is_file($item) && basename($item) !== '.gitkeep') {
                unlink($item);
                $removed++;
            }
        }

        $io->success("Cache dibersihkan ({$removed} file dihapus).");
        return 0;
    }
}
