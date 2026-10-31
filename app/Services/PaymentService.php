<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Repositories\AuditRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\SalesOrderRepository;
use App\Repositories\SequenceRepository;
use App\Support\IntegrityGateException;
use App\Support\PaymentCalculator;
use App\Validators\PaymentValidator;

/**
 * Logika bisnis Pembayaran (§8.H). Satu transaksi = satu baris log (uang masuk).
 * Penomoran lewat SequenceRepository (BUKAN MAX(id)+1), di dalam transaksi.
 * Aturan §9 ditegakkan di sini: jumlah tidak boleh melebihi sisa tagihan, dengan
 * sumber kebenaran "sudah dibayar" = SUM(payments) — dp_amount pesanan tidak
 * dihitung ulang (hanya gerbang produksi). Pembayaran dicatat manual (no gateway).
 */
final class PaymentService
{
    public const DOC_PREFIX = 'BYR';

    public function __construct(
        private readonly Database $db,
        private readonly PaymentRepository $payments,
        private readonly SalesOrderRepository $orders,
        private readonly AuditRepository $audit,
        private readonly SequenceRepository $sequences,
        private readonly PaymentValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        $errors = $this->validator->validate($data);

        $salesOrderId = (int) ($data['sales_order_id'] ?? 0);
        $order = $salesOrderId > 0 ? $this->orders->find($salesOrderId) : null;

        if ($order === null) {
            if (!isset($errors['sales_order_id'])) {
                $errors['sales_order_id'][] = 'Pesanan tidak ditemukan.';
            }
        } elseif ($order->status === SalesOrder::STATUS_CANCELLED) {
            $errors['sales_order_id'][] = 'Pesanan dibatalkan — tidak dapat menerima pembayaran.';
        }

        // Pagar §9: jumlah ≤ sisa tagihan. Dihitung otoritatif dari DB, bukan browser.
        $grandTotal = $order !== null ? sprintf('%.2f', $order->grandTotal) : '0.00';
        $paidTotal = $order !== null ? $this->payments->sumForSalesOrder($salesOrderId) : '0.00';
        $remaining = PaymentCalculator::remaining($grandTotal, $paidTotal);

        if ($order !== null && !isset($errors['amount'])) {
            $amount = PaymentCalculator::normalize($data['amount'] ?? '0');
            if (PaymentCalculator::exceeds($amount, $remaining)) {
                $errors['amount'][] = 'Jumlah melebihi sisa tagihan (sisa ' . money($remaining) . ').';
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $payload = $data;
        $payload['created_by'] = $userId;
        $amount = PaymentCalculator::normalize((string) ($data['amount'] ?? '0'));

        try {
            $id = $this->db->transaction(function () use ($payload, $userId, $order, $salesOrderId, $amount): int {
                // Kunci baris pesanan (§9, anti-bolong konkurensi): pembayaran serentak
                // atas pesanan yang sama diserialisasi lewat FOR UPDATE — permintaan
                // kedua membaca ulang sisa SETELAH yang pertama commit, sehingga pagar
                // "jumlah ≤ sisa tagihan" tak bisa ditembus double-submit. Cek di luar
                // transaksi hanya untuk UX; penegakan otoritatif ada di sini.
                $this->orders->lock($salesOrderId);

                $grandTotal = sprintf('%.2f', $order->grandTotal);
                $paidTotal = $this->payments->sumForSalesOrder($salesOrderId);
                $remaining = PaymentCalculator::remaining($grandTotal, $paidTotal);
                if (PaymentCalculator::exceeds($amount, $remaining)) {
                    throw new IntegrityGateException('amount', 'Jumlah melebihi sisa tagihan (sisa ' . money($remaining) . ').');
                }
                $isLunas = PaymentCalculator::isLunas($grandTotal, bcadd($paidTotal, $amount, 2));

                $payload['document_number'] = $this->sequences->nextDocumentNumber(self::DOC_PREFIX);
                $newId = $this->payments->create($payload);

                $this->audit->log(
                    $userId,
                    'payment.created',
                    'payment',
                    $newId,
                    sprintf(
                        'Pembayaran %s — pesanan %s: %s via %s%s',
                        Payment::phaseLabelFor((string) $payload['phase']),
                        (string) $order->documentNumber,
                        money((string) $payload['amount']),
                        Payment::methodLabelFor((string) $payload['method']),
                        $isLunas ? ' (LUNAS)' : '',
                    ),
                    [
                        'sales_order_id' => (int) $payload['sales_order_id'],
                        'amount' => (string) $payload['amount'],
                        'phase' => (string) $payload['phase'],
                        'method' => (string) $payload['method'],
                        'paid_before' => $paidTotal,
                        'grand_total' => $grandTotal,
                        'is_lunas' => $isLunas,
                    ],
                );

                return $newId;
            });
        } catch (IntegrityGateException $e) {
            // Transaksi sudah di-rollback Database::transaction(); petakan ke error form.
            return ['ok' => false, 'id' => null, 'errors' => [$e->field => [$e->getMessage()]]];
        }

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * Ringkasan saldo sebuah pesanan — untuk panel detail & status lunas.
     *
     * @return array{grand_total: string, paid_total: string, remaining: string, status: string, percent: float}
     */
    public function summaryFor(int $salesOrderId): array
    {
        $order = $this->orders->find($salesOrderId);
        $grandTotal = $order !== null ? sprintf('%.2f', $order->grandTotal) : '0.00';
        $paidTotal = $this->payments->sumForSalesOrder($salesOrderId);

        return [
            'grand_total' => $grandTotal,
            'paid_total' => $paidTotal,
            'remaining' => PaymentCalculator::remaining($grandTotal, $paidTotal),
            'status' => PaymentCalculator::status($grandTotal, $paidTotal),
            'percent' => PaymentCalculator::paidPercent($grandTotal, $paidTotal),
        ];
    }

    /** Riwayat pembayaran sebuah pesanan (terbaru lebih dulu). @return list<Payment> */
    public function historyFor(int $salesOrderId, int $limit = 30): array
    {
        return $this->payments->forSalesOrder($salesOrderId, $limit);
    }

    public function findOrFail(int $id): Payment
    {
        $payment = $this->payments->find($id);
        if ($payment === null) {
            throw new HttpException(404, 'Transaksi pembayaran tidak ditemukan.');
        }

        return $payment;
    }
}
