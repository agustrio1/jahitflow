<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class MakeControllerCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:controller {name}';
    }

    public function description(): string
    {
        return 'Buat class Controller baru';
    }

    protected function stubName(): string
    {
        return 'controller';
    }

    protected function namespace(): string
    {
        return 'App\\Http\\Controllers';
    }

    protected function subDirectory(): string
    {
        return 'app/Http/Controllers';
    }

    protected function classSuffix(): string
    {
        return 'Controller';
    }
}
