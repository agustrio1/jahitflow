<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Tabel pengguna aplikasi. Role dibatasi ke 4 value operasional
 * (keputusan Fase 0, §9.4). Password disimpan sebagai hash, bukan plaintext.
 */
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `users` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM(\'admin\',\'manager\',\'operator\',\'keuangan\') NOT NULL DEFAULT \'operator\',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_users_email (email),
                KEY idx_users_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `users`');
    }
}
