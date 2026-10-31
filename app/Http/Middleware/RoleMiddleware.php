<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Exceptions\AuthorizationException;
use App\Core\Middleware;
use App\Core\ParametricMiddleware;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use Closure;

/**
 * Gerbang berbasis role. Dipakai sebagai middleware route dengan
 * parameter, contoh: 'role:admin,manager'.
 *
 * Harus berjalan SETELAH AuthMiddleware (rute proteksi sudah menjamin
 * user login). Tanpa kecocokan role → 403 (§12 DOC).
 */
final class RoleMiddleware implements Middleware, ParametricMiddleware
{
    /** @var list<string> */
    private array $allowed = [];

    public function __construct(private readonly Auth $auth)
    {
    }

    public function withParam(string $param): void
    {
        $this->allowed = array_values(array_filter(array_map('trim', explode(',', $param))));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return $request->isHtmx()
                ? Response::htmxRedirect(route('login'))
                : Response::redirect(route('login'));
        }

        if ($this->allowed !== [] && !$user->hasRole($this->allowed)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk bagian ini.');
        }

        return $next($request);
    }
}
