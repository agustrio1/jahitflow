<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Audit trail perubahan data bisnis penting (§9 & §12 DOC).
 * Kolom meta menyimpan konteks tambahan (status lama/baru, field berubah)
 * sebagai JSON — query utama lewat entity + entity_id.
 */
class CreateAuditLogsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `audit_logs` (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                action VARCHAR(60) NOT NULL,
                entity VARCHAR(60) NOT NULL,
                entity_id INT UNSIGNED NULL,
                description VARCHAR(255) NOT NULL,
                meta JSON NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_audit_entity (entity, entity_id),
                KEY idx_audit_user (user_id),
                CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES `users`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `audit_logs`');
    }
}
