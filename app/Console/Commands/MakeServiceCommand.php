<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class MakeServiceCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:service {name}';
    }

    public function description(): string
    {
        return 'Buat class Service (business logic) baru';
    }

    protected function stubName(): string
    {
        return 'service';
    }

    protected function namespace(): string
    {
        return 'App\\Services';
    }

    protected function subDirectory(): string
    {
        return 'app/Services';
    }

    protected function classSuffix(): string
    {
        return 'Service';
    }
}
