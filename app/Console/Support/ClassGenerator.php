<?php

declare(strict_types=1);

namespace App\Console\Support;

use RuntimeException;

/**
 * Mesin tulis-file untuk generator `make:*`.
 *
 * Menyatukan: baca stub terpusat, pengisian placeholder {{...}},
 * pembuatan direktori bertingkat, dan PENOLAKAN menimpa file yang sudah
 * ada kecuali `--force` (aturan §5 DOC).
 */
final class ClassGenerator
{
    public function __construct(
        private readonly string $basePath,
        private readonly string $stubsPath,
    ) {
    }

    /**
     * @param array<string, string> $vars placeholder => nilai
     */
    public function render(string $stubName, array $vars): string
    {
        $stub = $this->stubsPath . '/' . $stubName . '.stub';
        if (!is_file($stub)) {
            throw new RuntimeException("Stub [{$stubName}] tidak ditemukan.");
        }

        $contents = (string) file_get_contents($stub);

        foreach ($vars as $key => $value) {
            $contents = str_replace('{{' . $key . '}}', $value, $contents);
        }

        return $contents;
    }

    /**
     * Tulis konten ke path relatif (dari project root).
     *
     * @return string path absolut file yang dibuat
     */
    public function write(string $relativePath, string $contents, bool $force): string
    {
        $absolute = $this->basePath . '/' . ltrim($relativePath, '/');

        if (file_exists($absolute) && !$force) {
            throw new RuntimeException(
                "File sudah ada: {$relativePath}. Tambahkan --force untuk menimpa."
            );
        }

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException("Gagal membuat direktori: {$dir}");
        }

        if (file_put_contents($absolute, $contents) === false) {
            throw new RuntimeException("Gagal menulis file: {$relativePath}");
        }

        return $absolute;
    }
}
