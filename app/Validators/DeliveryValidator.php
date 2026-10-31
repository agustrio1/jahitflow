<?php

declare(strict_types=1);

namespace App\Validators;

use App\Models\Delivery;

/**
 * Validasi bentuk catatan pengiriman (§8.I). MURNI — tanpa DB. Menjaga jumlah
 * bulat positif, tanggal sah, metode dari daftar valid, dan panjang teks. Status
 * TIDAK divalidasi di sini (ditetapkan service saat pembuatan; transisi dijaga
 * DeliveryFlow di service). Batas "≤ jumlah siap" butuh agregat → di DeliveryService.
 */
final class DeliveryValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $salesOrderId = (int) ($data['sales_order_id'] ?? 0);
        if ($salesOrderId <= 0) {
            $errors['sales_order_id'][] = 'Pilih pesanan yang dikirim.';
        }

        // Jumlah: bilangan bulat lebih dari nol.
        $quantity = trim((string) ($data['quantity'] ?? ''));
        if ($quantity === '' || preg_match('/^\d+$/', $quantity) !== 1) {
            $errors['quantity'][] = 'Jumlah harus angka bulat.';
        } elseif ((int) $quantity <= 0) {
            $errors['quantity'][] = 'Jumlah harus lebih besar dari nol.';
        }

        // Tanggal pengiriman: wajib, YYYY-MM-DD, benar-benar ada.
        $date = trim((string) ($data['delivery_date'] ?? ''));
        if ($date === '' || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1
            || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $errors['delivery_date'][] = 'Tanggal pengiriman tidak valid (format YYYY-MM-DD).';
        }

        $method = (string) ($data['method'] ?? '');
        if (!in_array($method, Delivery::METHODS, true)) {
            $errors['method'][] = 'Pilih metode/kurir pengiriman yang valid.';
        }

        $textFields = [
            'courier' => ['kurir', 120],
            'tracking_number' => ['nomor resi', 120],
            'recipient' => ['penerima', 150],
            'handover_ref' => ['bukti serah terima', 150],
            'note' => ['catatan', 255],
        ];
        foreach ($textFields as $key => [$label, $max]) {
            if (mb_strlen(trim((string) ($data[$key] ?? ''))) > $max) {
                $errors[$key][] = "{$label} maksimal {$max} karakter.";
            }
        }

        return $errors;
    }
}
