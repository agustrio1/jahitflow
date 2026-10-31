<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Pengiriman (§8.I DOC). Satu baris = satu batch pengiriman atas sebuah Sales
 * Order: jumlah dikirim, tanggal, kurir/metode, nomor resi, penerima, dan bukti
 * serah terima. Beda dari pembayaran/QC (log murni), record ini MEMILIKI status
 * yang bertransisi eksplisit (§9): disiapkan → dikirim → diterima, atau batal.
 *
 * Pagar §9 "tidak boleh dikirim melebihi jumlah yang siap & tersedia" ditegakkan
 * di DeliveryService (memerlukan agregat: min(total pesanan, total QC lolos) −
 * sudah dikirim non-batal). DB hanya menjaga bentuk dasar:
 *  - quantity > 0;
 *  - status & method dibatasi ENUM.
 * Jumlah (quantity) terkunci saat pembuatan; koreksi jumlah lewat pembatalan +
 * batch baru, bukan mengedit angka. Transisi status tercatat di audit trail.
 */
class CreateDeliveriesTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `deliveries` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_number VARCHAR(30) NOT NULL,
                sales_order_id INT UNSIGNED NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                delivery_date DATE NOT NULL,
                method ENUM(\'kurir\',\'ekspedisi\',\'antar_sendiri\',\'ambil_sendiri\',\'lainnya\') NOT NULL DEFAULT \'kurir\',
                courier VARCHAR(120) NULL,
                tracking_number VARCHAR(120) NULL,
                recipient VARCHAR(150) NULL,
                handover_ref VARCHAR(150) NULL,
                note VARCHAR(255) NULL,
                status ENUM(\'disiapkan\',\'dikirim\',\'diterima\',\'batal\') NOT NULL DEFAULT \'disiapkan\',
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_delivery_document (document_number),
                KEY idx_delivery_order (sales_order_id, delivery_date),
                KEY idx_delivery_status (status),
                CONSTRAINT fk_delivery_sales_order FOREIGN KEY (sales_order_id) REFERENCES `sales_orders`(id),
                CONSTRAINT fk_delivery_creator FOREIGN KEY (created_by) REFERENCES `users`(id) ON DELETE SET NULL,
                CONSTRAINT chk_delivery_quantity_positive CHECK (quantity > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `deliveries`');
    }
}
