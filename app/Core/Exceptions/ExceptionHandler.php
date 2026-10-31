<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use Throwable;

/**
 * Penanganan exception terpusat.
 *
 * Prinsip (§12 DOC): produksi tidak pernah membocorkan stack trace,
 * kredensial, atau detail internal. Detail hanya saat APP_DEBUG=true,
 * dan itupun log penuh selalu disimpan ke file.
 */
final class ExceptionHandler
{
    public function __construct(
        private readonly Logger $logger,
        private readonly bool $debug,
        private readonly View $view,
        private readonly Session $session,
    ) {
    }

    public function handle(Throwable $e, Request $request): Response
    {
        [$status, $title, $message] = $this->classify($e);

        // 5xx selalu dicatat; 4xx dicatat sebagai warning agar terlihat pola.
        if ($status >= 500) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
        } else {
            $this->logger->warning("HTTP {$status}: {$e->getMessage()}", [
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
        }

        if ($request->isHtmx()) {
            // Jawaban parsial agar HTMX bisa swap ke target tanpa reload penuh.
            $body = $this->renderError($status, $title, $message, $e, partial: true);
            return Response::html($body, $status);
        }

        $body = $this->renderError($status, $title, $message, $e, partial: false);
        return Response::html($body, $status);
    }

    /**
     * @return array{0: int, 1: string, 2: string} [status, title, message]
     */
    private function classify(Throwable $e): array
    {
        if ($e instanceof HttpException) {
            return [$e->statusCode(), (string) $e->statusCode(), $e->getMessage()];
        }

        if ($e instanceof AuthorizationException) {
            return [403, '403', $e->getMessage()];
        }

        if ($e instanceof ValidationException) {
            // Validasi seharusnya ditangani controller; bila lolos ke sini,
            // kembalikan 422 dengan pesan pertama saja (bukan daftar penuh).
            return [422, '422', $e->firstError() ?? 'Data yang dikirim tidak valid.'];
        }

        //Throwable tak terduga → 500, pesan generik di produksi.
        return [500, '500', $this->debug
            ? $e->getMessage()
            : 'Terjadi kesalahan pada server. Silakan coba lagi.'];
    }

    private function renderError(int $status, string $title, string $message, Throwable $e, bool $partial): string
    {
        $data = [
            'status' => $status,
            'title' => $title,
            'message' => $message,
            'debugTrace' => $this->debug && $status >= 500
                ? (string) $e
                : null,
            'csrfToken' => $this->session->token(),
        ];

        $template = $partial ? 'errors.minimal' : 'errors.page';

        try {
            return $this->view->render($template, $data);
        } catch (Throwable) {
            // Fallback bila sistem view ikut rusak (mis. view path salah).
            $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
            return "<!doctype html><meta charset='utf-8'><title>{$title}</title><h1>{$title}</h1><p>{$safe}</p>";
        }
    }
}
