<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Satu batch pengiriman (§8.I). Data-holder murni; quantity sudah divalidasi &
 * dibatasi di lapisan layanan. Kolom join (salesOrderNumber/customerName/
 * createdByName) diisi repository saat membaca.
 */
final class Delivery
{
    public const STATUS_DISIAPKAN = 'disiapkan';
    public const STATUS_DIKIRIM = 'dikirim';
    public const STATUS_DITERIMA = 'diterima';
    public const STATUS_BATAL = 'batal';

    public const METHOD_KURIR = 'kurir';
    public const METHOD_EKSPEDISI = 'ekspedisi';
    public const METHOD_ANTAR_SENDIRI = 'antar_sendiri';
    public const METHOD_AMBIL_SENDIRI = 'ambil_sendiri';
    public const METHOD_LAINNYA = 'lainnya';

    public const STATUSES = [self::STATUS_DISIAPKAN, self::STATUS_DIKIRIM, self::STATUS_DITERIMA, self::STATUS_BATAL];
    public const METHODS = [self::METHOD_KURIR, self::METHOD_EKSPEDISI, self::METHOD_ANTAR_SENDIRI, self::METHOD_AMBIL_SENDIRI, self::METHOD_LAINNYA];

    public function __construct(
        public readonly int $id,
        public readonly string $documentNumber,
        public readonly int $salesOrderId,
        public readonly int $quantity,
        public readonly string $deliveryDate,
        public readonly string $method,
        public readonly ?string $courier,
        public readonly ?string $trackingNumber,
        public readonly ?string $recipient,
        public readonly ?string $handoverRef,
        public readonly ?string $note,
        public readonly string $status,
        public readonly ?int $createdBy,
        public readonly string $createdAt = '',
        public readonly ?string $salesOrderNumber = null,
        public readonly ?string $customerName = null,
        public readonly ?string $createdByName = null,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) $row['id'],
            documentNumber: (string) $row['document_number'],
            salesOrderId: (int) $row['sales_order_id'],
            quantity: (int) $row['quantity'],
            deliveryDate: (string) ($row['delivery_date'] ?? ''),
            method: (string) $row['method'],
            courier: $row['courier'] ?? null,
            trackingNumber: $row['tracking_number'] ?? null,
            recipient: $row['recipient'] ?? null,
            handoverRef: $row['handover_ref'] ?? null,
            note: $row['note'] ?? null,
            status: (string) $row['status'],
            createdBy: isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            salesOrderNumber: $row['sales_order_number'] ?? null,
            customerName: $row['customer_name'] ?? null,
            createdByName: $row['created_by_name'] ?? null,
        );
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUS_BADGE[$this->status] ?? 'jf-badge--info';
    }

    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->method] ?? $this->method;
    }

    public static function statusLabelFor(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    public static function methodLabelFor(string $method): string
    {
        return self::METHOD_LABELS[$method] ?? $method;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_BATAL;
    }

    /** Status akhir — tidak dapat bertransisi lagi. */
    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_DITERIMA, self::STATUS_BATAL], true);
    }

    private const STATUS_LABELS = [
        self::STATUS_DISIAPKAN => 'Disiapkan',
        self::STATUS_DIKIRIM => 'Dikirim',
        self::STATUS_DITERIMA => 'Diterima',
        self::STATUS_BATAL => 'Dibatalkan',
    ];

    private const STATUS_BADGE = [
        self::STATUS_DISIAPKAN => 'jf-badge--info',
        self::STATUS_DIKIRIM => 'jf-badge--warning',
        self::STATUS_DITERIMA => 'jf-badge--success',
        self::STATUS_BATAL => 'jf-badge--danger',
    ];

    private const METHOD_LABELS = [
        self::METHOD_KURIR => 'Kurir',
        self::METHOD_EKSPEDISI => 'Ekspedisi',
        self::METHOD_ANTAR_SENDIRI => 'Antar Sendiri',
        self::METHOD_AMBIL_SENDIRI => 'Diambil Penerima',
        self::METHOD_LAINNYA => 'Lainnya',
    ];
}
