<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;
use Closure;

/**
 * Gerbang login. Tamu diarahkan ke halaman login dengan menyimpan URL
 * tujuan (intended) agar bisa dilanjutkan setelah login berhasil.
 */
final class AuthMiddleware implements Middleware
{
    public function __construct(
        private readonly Auth $auth,
        private readonly Session $session,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->auth->check()) {
            return $next($request);
        }

        if ($request->method() === 'GET') {
            $this->session->set('url.intended', $request->path());
        }

        if ($request->isHtmx()) {
            return Response::htmxRedirect(route('login'));
        }

        return Response::redirect(route('login'));
    }
}
