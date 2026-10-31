<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\BaseCommand;
use App\Console\ConsoleIO;
use App\Console\Support\ClassGenerator;
use RuntimeException;

/**
 * Base generator class ber-namespace (controller, model, service, dll.).
 *
 * Kelas anak cukup mendeklarasikan stub, namespace dasar, dan direktori target.
 * Nama class divalidasi, suffix otomatis dilengkapi, dan file yang sudah
 * ada ditolak kecuali `--force` (aturan §5 DOC).
 */
abstract class GeneratorCommand extends BaseCommand
{
    abstract protected function stubName(): string;

    /** Namespace dasar, contoh: "App\Http\Controllers". */
    abstract protected function namespace(): string;

    /** Direktori relatif project, contoh: "app/Http/Controllers". */
    abstract protected function subDirectory(): string;

    /** Suffix class yang dipaksa, contoh "Controller". Kosong = tidak ada. */
    protected function classSuffix(): string
    {
        return '';
    }

    /** @param array<string, string> $extra placeholder tambahan */
    protected function vars(string $class, array $extra = []): array
    {
        return $extra;
    }

    protected function afterWrite(string $class, ConsoleIO $io): void
    {
    }

    public function handle(array $args, ConsoleIO $io): int
    {
        $raw = $this->argument($args, 0);
        if ($raw === null || $raw === '') {
            throw new RuntimeException('Sebutkan nama class. Contoh: php bin/console ' . $this->signature() . ' OrderController');
        }

        $this->validateName($raw);
        [$subPath, $class] = $this->splitAndQualify($raw);

        $force = $this->hasOption($args, 'force');

        $namespace = $this->namespace();
        $relativeDir = $this->subDirectory();
        if ($subPath !== '') {
            $namespace .= '\\' . $this->studlyNested($subPath);
            $relativeDir .= '/' . $subPath;
        }

        $vars = array_merge([
            'namespace' => $namespace,
            'class' => $class,
        ], $this->vars($class));

        $generator = new ClassGenerator($this->kernel->basePath(), $this->kernel->basePath('app/Console/Stubs'));
        $contents = $generator->render($this->stubName(), $vars);

        $relative = $relativeDir . '/' . $class . '.php';
        $generator->write($relative, $contents, $force);

        $io->success("{$namespace}\\{$class}  →  {$relative}");
        $this->afterWrite($class, $io);

        return 0;
    }

    /**
     * Validasi dasar nama (mencegah path traversal / karakter berbahaya).
     * Boleh: huruf, angka, backslash, slash, underscore; harus diawali huruf.
     */
    private function validateName(string $name): void
    {
        if (str_contains($name, '..')) {
            throw new RuntimeException('Nama tidak boleh memuat "..".');
        }
        if (!preg_match('#^[A-Za-z][A-Za-z0-9_\/]*[A-Za-z0-9_]$#', $name)) {
            throw new RuntimeException("Nama [{$name}] tidak valid (huruf/angka/underscore/slash, diawali huruf).");
        }
    }

    /**
     * @return array{0: string, 1: string} [subPath (mis. "Admin"), Class]
     */
    private function splitAndQualify(string $name): array
    {
        $name = str_replace('\\', '/', $name);
        $parts = array_values(array_filter(explode('/', $name), static fn(string $p): bool => $p !== ''));
        $base = (string) array_pop($parts);
        $subPath = implode('/', $parts);

        $class = $this->studly($base);
        $suffix = $this->classSuffix();
        if ($suffix !== '' && !str_ends_with($class, $suffix)) {
            $class .= $suffix;
        }

        return [$subPath, $class];
    }

    private function studlyNested(string $subPath): string
    {
        return implode('\\', array_map(fn(string $p): string => $this->studly($p), array_filter(explode('/', $subPath))));
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }
}
