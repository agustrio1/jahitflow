<?php

declare(strict_types=1);

use App\Core\Migration;

/**
 * Foto referensi produk (§8.C). File disimpan PRIVAT di storage/uploads
 * (di luar document root) dan disajikan lewat route ter-autentikasi —
 * sesuai §12 DOC. Nama file di-hash, nama asli hanya untuk tampilan.
 */
class CreateProductPhotosTable extends Migration
{
    public function up(): void
    {
        $this->execute(
            'CREATE TABLE `product_photos` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id INT UNSIGNED NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                mime VARCHAR(60) NOT NULL,
                size_bytes INT UNSIGNED NOT NULL,
                created_by INT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_photos_product (product_id),
                CONSTRAINT fk_photos_product FOREIGN KEY (product_id) REFERENCES `products`(id) ON DELETE CASCADE,
                CONSTRAINT fk_photos_user FOREIGN KEY (created_by) REFERENCES `users`(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `product_photos`');
    }
}
