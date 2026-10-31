<?php

declare(strict_types=1);

/**
 * Bootstrap PHPUnit — hanya autoload; test unit tidak butuh app utuh.
 * Test feature/integration akan memanggil bootstrap/app.php sendiri
 * dengan DB tertiary (jahitflow_test) lewat environment phpunit.xml.
 */

require dirname(__DIR__) . '/vendor/autoload.php';
