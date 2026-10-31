<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Container_dependency injection sederhana berbasis factory closure.
 *
 * Sengaja tanpa auto-wiring reflection: pada skala aplikasi ini,
 * registrasi eksplisit lebih mudah dibaca dan diprediksi.
 */
final class Container
{
    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** Daftarkan factory untuk construction baru setiap kali resolve. */
    public function bind(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    /** Daftarkan singleton: factory hanya dieksekusi sekali. */
    public function singleton(string $id, Closure $factory): void
    {
        $this->bindings[$id] = $factory;
        $this->instances[$id] = null; // tandai singleton, belum di-resolve
    }

    /** Inject instance siap pakai (dipakai testing & bootstrap). */
    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    /**
     * Registrasi berbasis refleksi (App\Core\Factory) — praktis untuk layer
     * Fase 4 (repository/service/controller). Core tetap bind manual.
     */
    public function auto(string $class, bool $shared = true): void
    {
        $factory = fn(Container $c): object => Factory::wire($c, $class);
        $shared ? $this->singleton($class, $factory) : $this->bind($class, $factory);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    public function make(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            throw new RuntimeException("Tidak ada binding untuk [{$id}] di container.");
        }

        $object = ($this->bindings[$id])($this);

        // Simpan bila terdaftar sebagai singleton (instances di-set null saat bind).
        if (array_key_exists($id, $this->instances)) {
            $this->instances[$id] = $object;
        }

        return $object;
    }
}
