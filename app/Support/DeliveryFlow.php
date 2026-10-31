<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Delivery;

/**
 * Mesin status pengiriman (§9 DOC: state transition eksplisit). MURNI — tanpa DB.
 *
 *   disiapkan ──▶ dikirim ──▶ diterima   (akhir)
 *       │            │
 *       └──▶ batal ◀──┘                    (akhir)
 *
 * Sekali diterima/dibatalkan, tidak ada transisi lanjut. Peta transisi dijaga di
 * Service (penegakan nyata), kelas ini hanya menjawab "bolehkah A → B?".
 */
final class DeliveryFlow
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        Delivery::STATUS_DISIAPKAN => [Delivery::STATUS_DIKIRIM, Delivery::STATUS_BATAL],
        Delivery::STATUS_DIKIRIM => [Delivery::STATUS_DITERIMA, Delivery::STATUS_BATAL],
        Delivery::STATUS_DITERIMA => [],
        Delivery::STATUS_BATAL => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return list<string> status yang dapat dituju dari $from */
    public static function nextStates(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function isFinal(string $status): bool
    {
        return self::nextStates($status) === [];
    }

    /** Pesan penolakan ramah untuk flash/audit. */
    public static function rejectionMessage(string $from, string $to): string
    {
        return sprintf(
            'Transisi "%s" → "%s" tidak diizinkan.',
            Delivery::statusLabelFor($from),
            Delivery::statusLabelFor($to),
        );
    }
}
