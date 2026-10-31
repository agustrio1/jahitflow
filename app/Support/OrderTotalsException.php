<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Dilempar saat perhitungan total pesanan tidak valid (diskon/uang muka
 * di luar batas, kuantitas/harga cacat). Membawa `field` agar service bisa
 * memetakannya kembali ke error form yang relevan.
 */
final class OrderTotalsException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
