<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;

/**
 * Exception dengan status HTTP — dilempar dari mana saja, diterjemahkan
 * oleh ExceptionHandler menjadi halaman error yang tepat.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Permintaan tidak valid.',
            403 => 'Anda tidak memiliki akses untuk tindakan ini.',
            404 => 'Halaman yang Anda cari tidak ditemukan.',
            405 => 'Metode tidak diizinkan untuk URL ini.',
            419 => 'Sesi Anda telah kedaluwarsa. Muat ulang halaman dan coba lagi.',
            422 => 'Data yang dikirim tidak valid.',
            500 => 'Terjadi kesalahan pada server.',
            default => 'Terjadi kesalahan.',
        };
    }
}
