<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class MakeModelCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:model {name}';
    }

    public function description(): string
    {
        return 'Buat class Model (entity data holder) baru';
    }

    protected function stubName(): string
    {
        return 'model';
    }

    protected function namespace(): string
    {
        return 'App\\Models';
    }

    protected function subDirectory(): string
    {
        return 'app/Models';
    }
}
