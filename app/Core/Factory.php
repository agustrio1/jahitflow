<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Pabrik objek berbasis refleksi — khusus skala Fase 4 yang menambah
 * banyak class dengan dependensi bertipe class (Repository → Database,
 * Service → Repository, Controller → Service/Policy).
 *
 * Batasan sengaja konservatif:
 * - HANYA param bertipe class yang di-resolve otomatis (lewat container
 *   bila ada binding, lewat refleksi rekursif bila tidak).
 * - Param scalar WAJIB punya default value; kalau tidak → error jelas,
 *   bukan tebakan.
 * - Registrasi core (Fase 1-3) tetap eksplisit di bootstrap; factory
 *   ini opsional per class lewat Container::auto().
 */
final class Factory
{
    public static function wire(Container $container, string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Class [{$class}] tidak ditemukan untuk autowire.");
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dep = $type->getName();
                $args[] = $container->has($dep)
                    ? $container->make($dep)
                    : self::wire($container, $dep);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException(
                "Autowire tidak dapat mengisi \${$param->getName()} pada [{$class}] — "
                . 'dependensi scalar tanpa default harus di-bind manual di bootstrap.'
            );
        }

        return $reflection->newInstanceArgs($args);
    }
}
