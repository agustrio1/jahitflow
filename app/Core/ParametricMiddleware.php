<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Middleware yang menerima parameter dari route, mis. 'role:admin,manager'.
 * Application memisahkan 'Class:param' lalu memanggil withParam($param)
 * sebelum handle(). Memungkinkan satu kelas middleware dipakai dengan
 * konfigurasi berbeda per route tanpa duplikasi kelas.
 */
interface ParametricMiddleware
{
    public function withParam(string $param): void;
}
