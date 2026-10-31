<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => 'jahitflow_session',
    'path' => dirname(__DIR__) . '/storage/sessions',
    // Secure cookie: aktif hanya saat SESSION_SECURE=true (produksi HTTPS).
    'secure' => filter_var(Env::get('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOL),
    'samesite' => 'Lax',
    'lifetime' => 7200,
    'idle_timeout' => 7200,
];
