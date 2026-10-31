<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Pembayaran pelanggan (§8.H DOC). Setiap baris = satu transaksi uang masuk
 * yang menaut ke sebuah Sales Order: uang muka, cicilan (bertahap), atau
 * pelunasan. Entri bersifat LOG — sekali tercatat tidak diedit (riwayat tetap
 * utuh untuk audit); koreksi dibuat sebagai transaksi susulan (mis. lebih/kurang),
 * bukan mengubah baris lama.
 *
 * Sumber kebenaran "sudah diterima" = SUM(amount) per pesanan. dp_amount pada
 * sales_orders HANYA gerbang produksi dan tidak dihitung ulang di sini (§9).
 *
 * Batas "pembayaran tidak boleh melebihi sisa tagihan" ditegakkan di Service
 * (memerlukan agregat SUM lintas baris + grand_total). DB hanya menjaga bentuk dasar:
 *  - amount harus > 0 (tidak ada pembayaran nol/negatif di kolom ini).
 * Pencatatan manual — tidak ada gerbang pembayaran otomatis (§8.H).
 */
class CreatePaymentsTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `payments` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_number VARCHAR(30) NOT NULL,
                sales_order_id INT UNSIGNED NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                phase ENUM(\'uang_muka\',\'cicilan\',\'pelunasan\') NOT NULL DEFAULT \'cicilan\',
                method ENUM(\'tunai\',\'transfer\',\'giro\',\'kartu\',\'lainnya\') NOT NULL DEFAULT \'transfer\',
                reference VARCHAR(100) NULL,
                payment_date DATE NOT NULL,
                note VARCHAR(255) NULL,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_payment_document (document_number),
                KEY idx_payment_order (sales_order_id, payment_date),
                KEY idx_payment_method (method),
                CONSTRAINT fk_payment_sales_order FOREIGN KEY (sales_order_id) REFERENCES `sales_orders`(id),
                CONSTRAINT fk_payment_creator FOREIGN KEY (created_by) REFERENCES `users`(id) ON DELETE SET NULL,
                CONSTRAINT chk_payment_amount_positive CHECK (amount > 0)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `payments`');
    }
}
