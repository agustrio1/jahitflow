<?php

declare(strict_types=1);

namespace App\Validators;

use DateTime;

/**
 * Validasi input sales order (§8.D). Hanya memeriksa BENTUK & rentang dasar.
 * Uang muka/diskon hanya diverifikasi numerik non-negatif di sini; batas
 * terhadap total (mis. dp <= grand_total, diskon <= subtotal) ditegakkan
 * OrderTotals memakai harga varian dari DATABASE — bukan angka kiriman
 * browser. Kuantitas & variant_id item divalidasi strukturnya di sini.
 *
 * Shape input:
 *   customer_id, order_date, due_date, discount_amount, additional_cost,
 *   dp_amount, design_notes, items = list<{variant_id, quantity}>
 */
final class SalesOrderValidator
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, list<string>>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $customerId = $data['customer_id'] ?? null;
        if ($customerId === null || $customerId === '' || (int) $customerId <= 0) {
            $errors['customer_id'][] = 'Pilih pelanggan untuk pesanan ini.';
        }

        $orderDate = trim((string) ($data['order_date'] ?? ''));
        $dueDate = trim((string) ($data['due_date'] ?? ''));

        if (!$this->isDate($orderDate)) {
            $errors['order_date'][] = 'Tanggal pemesanan tidak valid.';
        }
        if (!$this->isDate($dueDate)) {
            $errors['due_date'][] = 'Tenggat (deadline) tidak valid.';
        }
        if ($this->isDate($orderDate) && $this->isDate($dueDate) && $dueDate < $orderDate) {
            $errors['due_date'][] = 'Tenggat tidak boleh lebih awal dari tanggal pemesanan.';
        }

        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            $errors['items'][] = 'Tambahkan minimal satu item pesanan.';
        } else {
            $errors['items'] = $this->validateItems($items);
        }

        foreach (['discount_amount' => 'Diskon', 'additional_cost' => 'Biaya tambahan', 'dp_amount' => 'Uang muka'] as $field => $label) {
            $value = $data[$field] ?? 0;
            if ($value === '' || $value === null) {
                $value = 0;
            }
            if (!is_numeric($value) || (float) $value < 0) {
                $errors[$field][] = "{$label} harus angka dan tidak boleh negatif.";
            } elseif ((float) $value > 999_999_999) {
                $errors[$field][] = "{$label} melebihi batas yang diizinkan.";
            }
        }

        $notes = trim((string) ($data['design_notes'] ?? ''));
        if (mb_strlen($notes) > 2000) {
            $errors['design_notes'][] = 'Catatan desain maksimal 2000 karakter.';
        }

        return $errors;
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<string>
     */
    private function validateItems(array $items): array
    {
        $errors = [];
        $seen = [];

        foreach (array_values($items) as $index => $item) {
            $row = $index + 1;

            if (!is_array($item)) {
                $errors[] = "Item ke-{$row} tidak valid.";
                continue;
            }

            $variantId = (int) ($item['variant_id'] ?? 0);
            if ($variantId <= 0) {
                $errors[] = "Item ke-{$row}: pilih varian produk.";
            } elseif (isset($seen[$variantId])) {
                $errors[] = "Item ke-{$row}: varian sama sudah ada di daftar — gabungkan jumlahnya.";
            } else {
                $seen[$variantId] = true;
            }

            $qty = $item['quantity'] ?? null;
            if (!is_numeric($qty) || (int) $qty <= 0) {
                $errors[] = "Item ke-{$row}: kuantitas harus angka bulat lebih dari nol.";
            }
        }

        return $errors;
    }

    private function isDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $parsed = DateTime::createFromFormat('Y-m-d', $value);

        return $parsed !== false && $parsed->format('Y-m-d') === $value;
    }
}
