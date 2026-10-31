<?php

declare(strict_types=1);

return [
    // Empat role operasional (keputusan Fase 0, poin 9.4).
    'roles' => [
        'admin' => 'Administrator',
        'manager' => 'Manajer',
        'operator' => 'Operator Produksi',
        'keuangan' => 'Keuangan',
    ],

    // Aturan uang muka (§8.D): produksi hanya mulai setelah DP >= persentase ini.
    'dp_min_percent' => 30,

    // Throttling login (§12): max percobaan lalu kunci selama lock_minutes.
    'login_throttle' => [
        'max_attempts' => 5,
        'lock_minutes' => 15,
    ],

    // Cost password_hash — default PHP saat ini (bcrypt 10); dievaluasi ulang
    // saat Fase 3 sesuai hardware target.
    'password_algo' => PASSWORD_DEFAULT,
    'password_cost' => 10,
];
