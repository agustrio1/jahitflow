<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * View renderer berbasis template PHP murni.
 *
 * - Layout dideklarasikan template lewat `$view->extend('layouts.app')`;
 *   isi halaman otomatis menjadi section `content`.
 * - Data diekstrak ke variabel lokal; template TIDAK boleh berisi
 *   query database atau business logic (dijaga lewat code review).
 */
final class View
{
    /** @var array<string, mixed> Data yang dibagikan ke semua template. */
    private array $shared = [];

    /** @var array<string, string> Sections yang sedang dikumpulkan. */
    private array $sections = [];

    private ?string $currentSection = null;

    public function __construct(private readonly string $viewPath)
    {
        // Template mengakses helper lewat variable $view (extend, yieldSection, dst.).
        $this->shared['view'] = $this;
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Render template dan kembalikan HTML.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $content = $this->renderFile($template, $data);

        // Layout dideklarasikan lewat $__layout di awal template.
        $layout = $this->sections['__layout'] ?? null;
        unset($this->sections['__layout']);

        if ($layout !== null && $layout !== $template) {
            $this->sections['content'] = $content;
            $content = $this->renderFile($layout, $data);
        }

        $this->sections = [];
        return $content;
    }

    /**
     * Hanya render isi (tanpa layout) — untuk partial jawaban HTMX.
     *
     * @param array<string, mixed> $data
     */
    public function partial(string $template, array $data = []): string
    {
        $content = $this->renderFile($template, $data);
        $this->sections = [];
        return $content;
    }

    /**
     * Sertakan partial di dalam template sedang berjalan.
     * Berbeda dengan partial(): sections TIDAK di-reset, sehingga aman
     * dipanggil dari layout saat proses render masih berlangsung.
     *
     * @param array<string, mixed> $data
     */
    public function include(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->resolve($template);

        $scope = array_merge($this->shared, $data);

        $level = ob_get_level();
        ob_start();

        try {
            $render = static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            };
            $render($file, $scope);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        $html = ob_get_clean();

        if ($this->currentSection !== null) {
            $this->sections[$this->currentSection] = ($this->sections[$this->currentSection] ?? '') . $html;
            return '';
        }

        return $html === false ? '' : $html;
    }

    private function resolve(string $template): string
    {
        $file = $this->viewPath . '/' . str_replace('.', '/', $template) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View [{$template}] tidak ditemukan di {$this->viewPath}");
        }

        return $file;
    }

    // ------------------------------------------------------------------
    // Helper untuk template
    // ------------------------------------------------------------------

    public function extend(string $layout): void
    {
        $this->sections['__layout'] = $layout;
    }

    public function startSection(string $name): void
    {
        $this->currentSection = $name;
        $this->sections[$name] = '';
    }

    public function endSection(): void
    {
        $this->currentSection = null;
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }
}
