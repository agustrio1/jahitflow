<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Dilempar saat suatu pergerakan stok tidak sah (arah tak dikenal, jumlah
 * nol/negatif pada penerimaan/pemakaian, atau hasil akan membuat saldo
 * negatif). Membawa `field` agar service memetakannya ke error form yang tepat.
 */
final class StockLedgerException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
