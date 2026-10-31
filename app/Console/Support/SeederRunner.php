<?php

declare(strict_types=1);

namespace App\Console\Support;

use App\Core\Seeder;
use PDO;
use RuntimeException;

/**
 * Engine seeder: menjalankan class Seeder (extends App\Core\Seeder) yang
 * berada di database/seeders. Nama class = StudlyCase dari nama file.
 */
final class SeederRunner
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $seedersPath,
    ) {
    }

    /** @return list<string> nama file seeder */
    public function files(): array
    {
        $files = glob($this->seedersPath . '/*.php') ?: [];
        return array_values(array_map(static fn(string $p): string => basename($p), $files));
    }

    /**
     * Jalankan satu seeder (atau semua). TIDAK dibungkus transaksi: seeder
     * boleh memakai truncate() yang memicu implicit commit di MySQL, sehingga
     * transaksi eksplisit justru konflik. Idempotensi jadi tanggung jawab
     * masing-masing seeder (via truncate/insertIfMissing).
     *
     * @param string|null $only nama file tanpa .php, null = semua
     * @return list<string>
     */
    public function seed(?string $only = null): array
    {
        $candidates = $only !== null ? [$only . '.php'] : $this->files();

        $ran = [];
        foreach ($candidates as $file) {
            $this->resolveSeeder($file)->run();
            $ran[] = basename($file, '.php');
        }

        return $ran;
    }

    private function resolveSeeder(string $file): Seeder
    {
        $path = $this->seedersPath . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException("Seeder [{$file}] tidak ditemukan.");
        }

        require_once $path;

        $class = str_replace(' ', '', ucwords(str_replace('_', ' ', basename($file, '.php'))));
        if (class_exists($class) && is_subclass_of($class, Seeder::class)) {
            return new $class($this->pdo);
        }

        throw new RuntimeException("File [{$file}] tidak memuat class extends App\\Core\\Seeder (diharapkan: {$class}).");
    }
}
