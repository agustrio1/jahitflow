<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use Closure;

/**
 * CSRF protection untuk semua request yang mengubah data.
 *
 * Bekerja sama untuk form biasa (field `_token`) maupun HTMX
 * (header `X-CSRF-Token` yang dikirim lewat hx-headers di layout).
 * Token tidak pernah divalidasi dari URL/query.
 */
final class CsrfMiddleware implements Middleware
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->method(), self::READ_METHODS, true)) {
            $token = $request->header('x-csrf-token') ?? $request->input(Session::TOKEN_KEY);

            if (!$this->session->validateToken(is_string($token) ? $token : null)) {
                // 419 = konvensi untuk "session/page expired" (bukan 403,
                // agar frontend bisa membedakan dari error izin).
                throw new HttpException(419);
            }
        }

        return $next($request);
    }
}
