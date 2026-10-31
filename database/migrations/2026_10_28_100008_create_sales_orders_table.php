<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Sales order / pesanan (§8.D DOC). Nomor otomatis unik (via sequensi,
 * BUKAN MAX(id)+1). Nilai uang desimal. Total tersimpan adalah HASIL
 * hitungan server dari harga varian di database — harga dari browser
 * tidak pernah dipercaya. dp_amount = uang muka; sisa dihitung turunan.
 * Status transisi eksplisit dijaga service (§9 DOC).
 */
class CreateSalesOrdersTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `sales_orders` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_number VARCHAR(30) NOT NULL,
                customer_id INT UNSIGNED NOT NULL,
                order_date DATE NOT NULL,
                due_date DATE NOT NULL,
                status ENUM(\'draft\',\'offered\',\'confirmed\',\'cancelled\') NOT NULL DEFAULT \'draft\',
                subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
                discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                additional_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
                grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
                dp_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
                design_notes TEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_so_number (document_number),
                KEY idx_so_customer (customer_id),
                KEY idx_so_status (status),
                KEY idx_so_due (due_date),
                CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES `customers`(id),
                CONSTRAINT fk_so_creator FOREIGN KEY (created_by) REFERENCES `users`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sales_orders`');
    }
}
