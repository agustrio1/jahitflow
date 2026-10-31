<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use Closure;

/**
 * Untuk route tamu (login). Pengguna yang sudah masuk tidak perlu
 * melihat form login lagi → lempar ke dashboard.
 */
final class GuestMiddleware implements Middleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(route('dashboard'));
        }

        return $next($request);
    }
}
