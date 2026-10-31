<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'jahitflow'),
    'username' => Env::get('DB_USERNAME', 'root'),
    // Kredensial hanya dari environment — tidak pernah hardcode di sini.
    'password' => Env::get('DB_PASSWORD', ''),
];
