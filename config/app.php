<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'JahitFlow'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => Env::get('APP_URL', 'http://localhost:8000'),
    'timezone' => 'Asia/Jakarta',
    'locale' => 'id_ID',
    'asset_version' => '1',

    // Kunci aplikasi (diisi acak di .env; dipakai fitur hashing/signed nanti).
    'key' => Env::get('APP_KEY', ''),
];
