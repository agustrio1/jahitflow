<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Pencatatan percobaan login untuk throttling (§12 DOC).
 * Dikunci berdasarkan email; locked_until menandai akhir pemblokiran.
 */
class CreateLoginAttemptsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `login_attempts` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(190) NOT NULL,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                locked_until DATETIME NULL,
                last_ip VARCHAR(45) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_login_attempts_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `login_attempts`');
    }
}
