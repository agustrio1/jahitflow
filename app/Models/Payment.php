<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Satu transaksi pembayaran pelanggan (§8.H). Data-holder murni; amount disimpan
 * sebagai string desimal (money) — pembulatan & batas "tidak melebihi sisa tagihan"
 * ditangani PaymentCalculator/PaymentService. Kolom join (salesOrderNumber/
 * customerName/createdByName) diisi repository saat membaca.
 */
final class Payment
{
    public const PHASE_UANG_MUKA = 'uang_muka';
    public const PHASE_CICILAN = 'cicilan';
    public const PHASE_PELUNASAN = 'pelunasan';

    public const METHOD_TUNAI = 'tunai';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_GIRO = 'giro';
    public const METHOD_KARTU = 'kartu';
    public const METHOD_LAINNYA = 'lainnya';

    /** Daftar fase & metode valid untuk validasi input. */
    public const PHASES = [self::PHASE_UANG_MUKA, self::PHASE_CICILAN, self::PHASE_PELUNASAN];
    public const METHODS = [self::METHOD_TUNAI, self::METHOD_TRANSFER, self::METHOD_GIRO, self::METHOD_KARTU, self::METHOD_LAINNYA];

    public function __construct(
        public readonly int $id,
        public readonly string $documentNumber,
        public readonly int $salesOrderId,
        public readonly string $amount,
        public readonly string $phase,
        public readonly string $method,
        public readonly ?string $reference,
        public readonly string $paymentDate,
        public readonly ?string $note,
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
            amount: (string) ($row['amount'] ?? '0.00'),
            phase: (string) $row['phase'],
            method: (string) $row['method'],
            reference: $row['reference'] ?? null,
            paymentDate: (string) ($row['payment_date'] ?? ''),
            note: $row['note'] ?? null,
            createdBy: isset($row['created_by']) && $row['created_by'] !== null ? (int) $row['created_by'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            salesOrderNumber: $row['sales_order_number'] ?? null,
            customerName: $row['customer_name'] ?? null,
            createdByName: $row['created_by_name'] ?? null,
        );
    }

    public function phaseLabel(): string
    {
        return self::PHASE_LABELS[$this->phase] ?? $this->phase;
    }

    public function methodLabel(): string
    {
        return self::METHOD_LABELS[$this->method] ?? $this->method;
    }

    public static function phaseLabelFor(string $phase): string
    {
        return self::PHASE_LABELS[$phase] ?? $phase;
    }

    public static function methodLabelFor(string $method): string
    {
        return self::METHOD_LABELS[$method] ?? $method;
    }

    /** Status badge fase pembayaran untuk UI. */
    public function phaseBadge(): string
    {
        return self::PHASE_BADGE[$this->phase] ?? 'jf-badge--info';
    }

    public function isSettlement(): bool
    {
        return $this->phase === self::PHASE_PELUNASAN;
    }

    private const PHASE_LABELS = [
        self::PHASE_UANG_MUKA => 'Uang Muka',
        self::PHASE_CICILAN => 'Cicilan',
        self::PHASE_PELUNASAN => 'Pelunasan',
    ];

    private const METHOD_LABELS = [
        self::METHOD_TUNAI => 'Tunai',
        self::METHOD_TRANSFER => 'Transfer',
        self::METHOD_GIRO => 'Giro',
        self::METHOD_KARTU => 'Kartu',
        self::METHOD_LAINNYA => 'Lainnya',
    ];

    private const PHASE_BADGE = [
        self::PHASE_UANG_MUKA => 'jf-badge--info',
        self::PHASE_CICILAN => 'jf-badge--warning',
        self::PHASE_PELUNASAN => 'jf-badge--success',
    ];
}
