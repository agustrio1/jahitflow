<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Mesin transisi tahap produksi — MURNI (tanpa DB). Memodelkan pipeline
 * berurutan (§8.E): satu tahap hanya boleh dikerjakan setelah tahap-tahap
 * sebelumnya selesai. Memeriksa daftar status (indeks = urutan posisi).
 *
 * Status: pending → in_progress → done. Metode *Reason() mengembalikan pesan
 * Bahasa Indonesia saat transisi DILARANG, atau null saat diizinkan — dipakai
 * service untuk memetakan ke error form; wrapper boolean untuk pemeriksaan cepat.
 */
final class ProductionStageMachine
{
    public const PENDING = 'pending';
    public const IN_PROGRESS = 'in_progress';
    public const DONE = 'done';

    /**
     * @param list<string> $statuses  status tiap tahap menurut urutan posisi
     */
    public static function canStart(array $statuses, int $index): bool
    {
        return self::startReason($statuses, $index) === null;
    }

    /** @param list<string> $statuses */
    public static function startReason(array $statuses, int $index): ?string
    {
        $current = $statuses[$index] ?? null;
        if ($current === null) {
            return 'Tahap tidak ditemukan.';
        }
        if ($current !== self::PENDING) {
            return 'Tahap ini sudah dimulai atau selesai.';
        }

        foreach (array_slice($statuses, 0, $index) as $position => $status) {
            if ($status !== self::DONE) {
                return 'Selesaikan tahap sebelumnya sebelum memulai tahap ini.';
            }
        }

        return null;
    }

    /** @param list<string> $statuses */
    public static function canComplete(array $statuses, int $index): bool
    {
        return self::completeReason($statuses, $index) === null;
    }

    /** @param list<string> $statuses */
    public static function completeReason(array $statuses, int $index): ?string
    {
        $current = $statuses[$index] ?? null;
        if ($current === null) {
            return 'Tahap tidak ditemukan.';
        }
        if ($current === self::PENDING) {
            return 'Mulai tahap ini sebelum menandai selesai.';
        }
        if ($current === self::DONE) {
            return 'Tahap ini sudah selesai.';
        }

        return null;
    }

    /** @param list<string> $statuses */
    public static function canReopen(array $statuses, int $index): bool
    {
        return self::reopenReason($statuses, $index) === null;
    }

    /**
     * Membuka kembali tahap selesai hanya bila belum ada tahap berikutnya
     * yang dikerjakan — menjaga konsistensi pipeline.
     *
     * @param list<string> $statuses
     */
    public static function reopenReason(array $statuses, int $index): ?string
    {
        $current = $statuses[$index] ?? null;
        if ($current === null) {
            return 'Tahap tidak ditemukan.';
        }
        if ($current !== self::DONE) {
            return 'Hanya tahap selesai yang dapat dibuka kembali.';
        }

        foreach (array_slice($statuses, $index + 1) as $status) {
            if ($status !== self::PENDING) {
                return 'Tidak dapat membuka tahap yang sudah selesai bila tahap berikutnya telah dikerjakan.';
            }
        }

        return null;
    }

    /** @param list<string> $statuses */
    public static function isComplete(array $statuses): bool
    {
        return $statuses !== [] && !in_array(self::PENDING, $statuses, true) && !in_array(self::IN_PROGRESS, $statuses, true);
    }

    /**
     * Tentukan status perintah produksi dari gabungan status tahap:
     *  done semua → completed; ada yang berjalan/selesai → running; selainnya queued.
     *
     * @param list<string> $statuses
     */
    public static function deriveOrderStatus(array $statuses): string
    {
        if (self::isComplete($statuses)) {
            return 'completed';
        }

        foreach ($statuses as $status) {
            if ($status !== self::PENDING) {
                return 'running';
            }
        }

        return 'queued';
    }
}
