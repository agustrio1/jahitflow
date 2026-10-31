<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Delivery;
use App\Models\ProductionOrder;
use App\Models\SalesOrder;
use App\Repositories\DashboardRepository;

/**
 * Menyusun model view dashboard (§8.A). Memanggil DashboardRepository lalu
 * memperkaya baris dengan label/badge dan penghitung hari-ke-tenggat agar view
 * tetap tipis (tanpa logika bisnis). Read-only.
 */
final class DashboardService
{
    public function __construct(private readonly DashboardRepository $dashboard)
    {
    }

    /**
     * @return array{
     *   counts: array<string,int>,
     *   money: array{order_value:string, received:string, receivable:string},
     *   dueSoon: list<array<string,mixed>>,
     *   overdue: list<array<string,mixed>>,
     *   awaitingQc: list<array<string,mixed>>,
     *   openDeliveries: list<array<string,mixed>>
     * }
     */
    public function compose(): array
    {
        return [
            'counts' => $this->dashboard->counts(),
            'money' => $this->dashboard->money(),
            'dueSoon' => $this->decorateOrders($this->dashboard->dueSoonOrders()),
            'overdue' => $this->decorateOrders($this->dashboard->overdueOrders()),
            'awaitingQc' => $this->decorateProduction($this->dashboard->awaitingQc()),
            'openDeliveries' => $this->decorateDeliveries($this->dashboard->openDeliveries()),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorateOrders(array $rows): array
    {
        $badges = [
            SalesOrder::STATUS_DRAFT => 'jf-badge--info',
            SalesOrder::STATUS_OFFERED => 'jf-badge--warning',
            SalesOrder::STATUS_CONFIRMED => 'jf-badge--success',
            SalesOrder::STATUS_CANCELLED => 'jf-badge--danger',
        ];

        foreach ($rows as &$row) {
            $status = (string) $row['status'];
            $row['status_label'] = SalesOrder::labelFor($status);
            $row['status_badge'] = $badges[$status] ?? 'jf-badge--info';
            $row['due_in_days'] = $this->daysUntil((string) $row['due_date']);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorateProduction(array $rows): array
    {
        $badges = [
            ProductionOrder::STATUS_QUEUED => 'jf-badge--info',
            ProductionOrder::STATUS_RUNNING => 'jf-badge--warning',
            ProductionOrder::STATUS_COMPLETED => 'jf-badge--success',
            ProductionOrder::STATUS_CANCELLED => 'jf-badge--danger',
        ];

        foreach ($rows as &$row) {
            $status = (string) $row['status'];
            $row['status_label'] = ProductionOrder::labelFor($status);
            $row['status_badge'] = $badges[$status] ?? 'jf-badge--info';
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function decorateDeliveries(array $rows): array
    {
        $badges = [
            Delivery::STATUS_DISIAPKAN => 'jf-badge--info',
            Delivery::STATUS_DIKIRIM => 'jf-badge--warning',
            Delivery::STATUS_DITERIMA => 'jf-badge--success',
            Delivery::STATUS_BATAL => 'jf-badge--danger',
        ];

        foreach ($rows as &$row) {
            $status = (string) $row['status'];
            $row['status_label'] = Delivery::statusLabelFor($status);
            $row['status_badge'] = $badges[$status] ?? 'jf-badge--info';
        }

        return $rows;
    }

    /** Selisih hari dari hari ini (negatif = sudah lewat). */
    private function daysUntil(string $date): int
    {
        $today = new \DateTimeImmutable('today');
        $target = new \DateTimeImmutable($date);

        return (int) $today->diff($target)->format('%r%a');
    }
}
