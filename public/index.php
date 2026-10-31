<?php

declare(strict_types=1);

/**
 * Front controller — satu-satunya file yang boleh diakses publik.
 * Document root web server HARUS menunjuk ke folder public/ ini,
 * sehingga source, .env, storage, dan config tidak terjangkau URL.
 */

use App\Core\Request;

$application = require dirname(__DIR__) . '/bootstrap/app.php';

$response = $application->handle(Request::capture());

$response->send();
