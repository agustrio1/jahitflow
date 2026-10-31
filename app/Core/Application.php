<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\ExceptionHandler;
use App\Core\Exceptions\HttpException;
use App\Services\Auth;
use Closure;
use RuntimeException;
use Throwable;

/**
 * Kernel aplikasi: satu siklus request → response.
 *
 * Urutan: session start → bagikan data view global → route match →
 * middleware pipeline (global + route) → controller → response.
 * Semua throwable ditangkap dan diteruskan ke ExceptionHandler.
 */
final class Application
{
    private static ?self $instance = null;

    /**
     * @param list<string> $globalMiddleware class name Middleware, urut eksekusi
     */
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly Session $session,
        private readonly View $view,
        private readonly ExceptionHandler $errorHandler,
        private readonly array $globalMiddleware = [],
    ) {
        self::$instance = $this;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Application belum di-bootstrap.');
        }
        return self::$instance;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function handle(Request $request): Response
    {
        try {
            $this->session->start();
            $this->shareViewGlobals($request);

            $match = $this->router->match($request->method(), $request->path());

            if ($match === null) {
                throw new HttpException($this->router->pathExists($request->path()) ? 405 : 404);
            }

            $request->setRouteParams($match['params']);

            $pipeline = $this->buildPipeline(
                $match['middleware'],
                fn (Request $r): Response => $this->runAction($match['action'], $r),
            );

            $response = $pipeline($request);
        } catch (Throwable $e) {
            $response = $this->errorHandler->handle($e, $request);
        }

        return $response;
    }

    /**
     * Rangkai middleware global + route menjadi satu closure berurutan.
     *
     * @param list<string> $routeMiddleware
     */
    private function buildPipeline(array $routeMiddleware, Closure $destination): Closure
    {
        $middleware = [...$this->globalMiddleware, ...$routeMiddleware];

        return array_reduce(
            array_reverse($middleware),
            fn (Closure $next, string $spec): Closure => function (Request $request) use ($spec, $next): Response {
                return $this->middlewareInstance($spec)->handle($request, $next);
            },
            $destination,
        );
    }

    /**
     * Resolve middleware, dukung format 'Kelas:param' (mis. 'role:admin,manager').
     * Instance dibuat per request agar middleware stateful (RoleMiddleware)
     * tidak saling mencemari antar route.
     */
    private function middlewareInstance(string $spec): Middleware
    {
        $class = $spec;
        $param = null;
        if (str_contains($spec, ':')) {
            [$class, $param] = explode(':', $spec, 2);
        }

        /** @var Middleware $instance */
        $instance = $this->container->make($class);
        if ($param !== null && $instance instanceof ParametricMiddleware) {
            $instance->withParam($param);
        }

        return $instance;
    }

    /**
     * Aksi format "Fully\\Qualified\\Controller@method".
     * Controller di-resolve lewat container agar dependensinya terdaftar eksplisit.
     */
    private function runAction(string $action, Request $request): Response
    {
        [$class, $method] = array_pad(explode('@', $action, 2), 2, 'index');

        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new HttpException(500, "Aksi route [{$action}] tidak valid.");
        }

        /** @var object $controller */
        $controller = $this->container->has($class)
            ? $this->container->make($class)
            : new $class();

        $result = $controller->{$method}($request);

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        throw new HttpException(500, 'Controller harus mengembalikan Response atau string HTML.');
    }

    /** Data yang selalu tersedia di setiap template. */
    private function shareViewGlobals(Request $request): void
    {
        $this->view->share('csrfToken', $this->session->token());
        $this->view->share('flashMessages', $this->session->pullFlash());
        $this->view->share('oldInput', $this->session->pullOldInput());
        $this->view->share('fieldErrors', $this->session->pullErrors());
        $cfg = $this->container->make(Config::class);
        $this->view->share('appEnv', $cfg->get('app.env', 'local'));
        $this->view->share('navigation', $cfg->get('navigation.sections', []));
        $this->view->share('currentPath', '/' . trim($request->path(), '/'));

        // Pengguna yang sedang login tersedia di seluruh layout (sidebar/topbar).
        // user() hanya menyentuh DB bila ada id di session — aman saat DB mati.
        $authUser = null;
        if ($this->container->has(Auth::class)) {
            try {
                $authUser = $this->container->make(Auth::class)->user();
            } catch (Throwable) {
                $authUser = null;
            }
        }
        $this->view->share('authUser', $authUser);
    }
}
