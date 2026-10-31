<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Pelanggan perorangan/perusahaan (§8.B DOC).
 * Kontak, alamat, catatan kebutuhan khusus. Soft delete via is_active —
 * data pelanggan tidak pernah dihapus keras agar riwayat pesanan utuh.
 */
class CreateCustomersTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `customers` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                type ENUM(\'perorangan\',\'perusahaan\') NOT NULL DEFAULT \'perorangan\',
                contact_person VARCHAR(120) NULL,
                phone VARCHAR(30) NOT NULL,
                email VARCHAR(190) NULL,
                address TEXT NULL,
                city VARCHAR(100) NULL,
                notes TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_customers_name (name),
                KEY idx_customers_phone (phone),
                KEY idx_customers_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `customers`');
    }
}
