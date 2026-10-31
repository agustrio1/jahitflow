<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class MakeMiddlewareCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:middleware {name}';
    }

    public function description(): string
    {
        return 'Buat class Middleware baru';
    }

    protected function stubName(): string
    {
        return 'middleware';
    }

    protected function namespace(): string
    {
        return 'App\\Http\\Middleware';
    }

    protected function subDirectory(): string
    {
        return 'app/Http/Middleware';
    }

    protected function classSuffix(): string
    {
        return 'Middleware';
    }
}
