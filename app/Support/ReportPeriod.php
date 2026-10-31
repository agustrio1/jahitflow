<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Rentang periode laporan (§8.J). MURNI — tanpa DB. Mentralkan normalisasi
 * filter tanggal: input mentah (bisa kosong/invalid) dinormalisasi ke tanggal
 * Y-m-d sah dengan default 30 hari terakhir; rentang dibalik bila from > to.
 *
 * Kenapa default 30 hari: laporan operasional paling sering dilihat per bulan
 * berjalan; pengguna tetap dapat melebarkan rentang lewat form.
 */
final class ReportPeriod
{
    public const DEFAULT_DAYS = 30;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
    }

    /**
     * Bangun periode dari input mentah. Nilai tak sah diganti default, sehingga
     * query tidak pernah menerima string sembarang (pared-to-parameter tetap aman).
     */
    public static function resolve(?string $rawFrom, ?string $rawTo): self
    {
        $today = date('Y-m-d');
        $defaultFrom = date('Y-m-d', strtotime('-' . (self::DEFAULT_DAYS - 1) . ' day'));

        $from = self::isValid($rawFrom) ? (string) $rawFrom : $defaultFrom;
        $to = self::isValid($rawTo) ? (string) $rawTo : $today;

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return new self($from, $to);
    }

    /** Batas bawah untuk kolom DATE (inklusif). */
    public function fromDate(): string
    {
        return $this->from;
    }

    /** Batas atas untuk kolom DATE (inklusif). */
    public function toDate(): string
    {
        return $this->to;
    }

    /** Batas bawah untuk kolom DATETIME (00:00:00). */
    public function fromDateTime(): string
    {
        return $this->from . ' 00:00:00';
    }

    /** Batas atas untuk kolom DATETIME (23:59:59). */
    public function toDateTime(): string
    {
        return $this->to . ' 23:59:59';
    }

    /** Jumlah hari inklusif dalam rentang. */
    public function days(): int
    {
        $a = strtotime($this->from);
        $b = strtotime($this->to);

        return (int) (round(($b - $a) / 86400)) + 1;
    }

    /** Apakah periode masih memakai default 30 hari terakhir (untuk petunjuk UI). */
    public function isDefault(): bool
    {
        $defaultFrom = date('Y-m-d', strtotime('-' . (self::DEFAULT_DAYS - 1) . ' day'));

        return $this->from === $defaultFrom && $this->to === date('Y-m-d');
    }

    private static function isValid(?string $date): bool
    {
        if ($date === null || $date === '') {
            return false;
        }

        $m = [];
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
