<?php

declare(strict_types=1);

namespace App\Console\Commands;

final class MakeValidatorCommand extends GeneratorCommand
{
    public function signature(): string
    {
        return 'make:validator {name}';
    }

    public function description(): string
    {
        return 'Buat class Validator baru';
    }

    protected function stubName(): string
    {
        return 'validator';
    }

    protected function namespace(): string
    {
        return 'App\\Validators';
    }

    protected function subDirectory(): string
    {
        return 'app/Validators';
    }

    protected function classSuffix(): string
    {
        return 'Validator';
    }
}
