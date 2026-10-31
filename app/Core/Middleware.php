<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Kontrak middleware: proses Request, kembalikan Response.
 * $next meneruskan ke middleware/controller berikutnya dalam pipeline.
 */
interface Middleware
{
    public function handle(Request $request, Closure $next): Response;
}
