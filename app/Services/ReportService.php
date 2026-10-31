<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\ProductionOrder;
use App\Models\QcInspection;
use App\Models\SalesOrder;
use App\Repositories\ReportRepository;
use App\Support\Csv;
use App\Support\ReportPeriod;

/**
 * Menyusun Laporan (§8.J) menjadi deskriptor siap-render. Service ini TIDAK
 * menyentuh HTML — hanya menggabungkan agregat repository menjadi KPI + tabel
 * bertipe, plus note definisi agar angka jujur (mis. "nilai pesanan" tidak pernah
 * disebut omzet diterima; pendapatan diterima = SUM pembayaran).
 *
 * Bentuk deskriptor dipakai bersama oleh view (reports/show) dan ekspor CSV
 * (App\Support\Csv) — satu sumber kebenaran, dua penyaji.
 */
final class ReportService
{
    public function __construct(private readonly ReportRepository $reports)
    {
    }

    /**
     * Metadata laporan yang tersedia untuk hub (/laporan).
     *
     * @return list<array{key: string, label: string, desc: string, icon: string}>
     */
    public function available(): array
    {
        return [
            ['key' => 'pesanan', 'label' => 'Pesanan per Periode', 'desc' => 'Volume & nilai pesanan (draft s.d. dikonfirmasi) berdasar tanggal pesanan.', 'icon' => 'cart'],
            ['key' => 'produksi', 'label' => 'Progres Produksi', 'desc' => 'Status perintah produksi dan pekerjaan yang lewat target.', 'icon' => 'factory'],
            ['key' => 'bahan', 'label' => 'Pemakaian Bahan', 'desc' => 'Total bahan keluar (dipakai) per jenis pada periode.', 'icon' => 'layers'],
            ['key' => 'qc', 'label' => 'Hasil Quality Control', 'desc' => 'Barang diperiksa, lulus, gagal, dan tingkat kelulusan.', 'icon' => 'check'],
            ['key' => 'pembayaran', 'label' => 'Pembayaran & Tunggakan', 'desc' => 'Uang masuk (pendapatan diterima) dan piutang pesanan berjalan.', 'icon' => 'wallet'],
            ['key' => 'pengiriman', 'label' => 'Pengiriman', 'desc' => 'Batch dan pcs terkirim per status pengiriman.', 'icon' => 'truck'],
        ];
    }

    public function exists(string $key): bool
    {
        foreach ($this->available() as $r) {
            if ($r['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bangun deskriptor laporan.
     *
     * @return array<string, mixed>
     */
    public function build(string $key, ReportPeriod $period): array
    {
        $meta = null;
        foreach ($this->available() as $r) {
            if ($r['key'] === $key) {
                $meta = $r;
                break;
            }
        }

        if ($meta === null) {
            throw new HttpException(404, 'Laporan tidak ditemukan.');
        }

        $descriptor = match ($key) {
            'pesanan' => $this->orders($period),
            'produksi' => $this->production($period),
            'bahan' => $this->materialUsage($period),
            'qc' => $this->qc($period),
            'pembayaran' => $this->payments($period),
            'pengiriman' => $this->deliveries($period),
        };

        return array_merge($descriptor, [
            'key' => $key,
            'title' => $meta['label'],
            'icon' => $meta['icon'],
            'period' => [
                'from' => $period->fromDate(),
                'to' => $period->toDate(),
                'days' => $period->days(),
                'isDefault' => $period->isDefault(),
            ],
        ]);
    }

    /** Ekspor CSV memakai deskriptor yang sama dengan layar. */
    public function csv(string $key, ReportPeriod $period): string
    {
        $d = $this->build($key, $period);

        return Csv::build($d['title'], $d['period'], $d['sections']);
    }

    // ------------------------------------------------------------------
    // Builders per laporan
    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function orders(ReportPeriod $p): array
    {
        $rows = $this->reports->orders($p);

        $countBy = [];
        $nilaiBy = [];
        foreach ($rows['byStatus'] as $r) {
            $countBy[(string) $r['status']] = (int) $r['total_orders'];
            $nilaiBy[(string) $r['status']] = (string) $r['nilai'];
        }

        $cancelled = $countBy[SalesOrder::STATUS_CANCELLED] ?? 0;
        $confirmed = $countBy[SalesOrder::STATUS_CONFIRMED] ?? 0;
        $totalAll = array_sum($countBy);
        $nonCancelled = $totalAll - $cancelled;

        $nilaiNonCancelled = '0.00';
        foreach ($nilaiBy as $status => $v) {
            if ($status !== SalesOrder::STATUS_CANCELLED) {
                $nilaiNonCancelled = bcadd($nilaiNonCancelled, $v, 2);
            }
        }

        $statusRows = array_map(static fn(array $r): array => [
            'status' => SalesOrder::labelFor((string) $r['status']),
            'jumlah' => (int) $r['total_orders'],
            'nilai' => (string) $r['nilai'],
        ], $rows['byStatus']);

        $monthRows = array_map(static fn(array $r): array => [
            'bulan' => (string) $r['bulan'],
            'jumlah' => (int) $r['total_orders'],
            'nilai' => (string) $r['nilai'],
        ], $rows['byMonth']);

        return [
            'subtitle' => 'Volume dan nilai pesanan pada rentang tanggal pesanan.',
            'note' => '"Nilai pesanan" adalah nilai kontrak (grand_total pesanan non-batal), BUKAN omzet yang sudah diterima. Pendapatan diterima dilihat di laporan Pembayaran.',
            'kpis' => [
                $this->kpi('Pesanan (non-batal)', $this->int($nonCancelled)),
                $this->kpi('Nilai pesanan', money($nilaiNonCancelled), 'nilai kontrak, bukan omzet diterima'),
                $this->kpi('Dikonfirmasi', $this->int($confirmed)),
                $this->kpi('Dibatalkan', $this->int($cancelled)),
            ],
            'sections' => [
                [
                    'caption' => 'Rekap per status',
                    'columns' => [
                        ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
                        ['key' => 'jumlah', 'label' => 'Jumlah', 'type' => 'int', 'align' => 'right'],
                        ['key' => 'nilai', 'label' => 'Nilai', 'type' => 'money', 'align' => 'right'],
                    ],
                    'rows' => $statusRows,
                    'empty' => 'Belum ada pesanan pada periode ini.',
                    'chart' => ['label' => 'status', 'value' => 'jumlah', 'type' => 'int'],
                ],
                [
                    'caption' => 'Tren bulanan (non-batal)',
                    'columns' => [
                        ['key' => 'bulan', 'label' => 'Bulan', 'type' => 'text'],
                        ['key' => 'jumlah', 'label' => 'Pesanan', 'type' => 'int', 'align' => 'right'],
                        ['key' => 'nilai', 'label' => 'Nilai', 'type' => 'money', 'align' => 'right'],
                    ],
                    'rows' => $monthRows,
                    'empty' => 'Tidak ada pesanan non-batal pada periode ini.',
                    'chart' => ['label' => 'bulan', 'value' => 'jumlah', 'type' => 'int'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function production(ReportPeriod $p): array
    {
        $rows = $this->reports->production($p);

        $countBy = [];
        foreach ($rows['byStatus'] as $r) {
            $countBy[(string) $r['status']] = (int) $r['total'];
        }
        $total = array_sum($countBy);

        $statusRows = array_map(static fn(array $r): array => [
            'status' => ProductionOrder::labelFor((string) $r['status']),
            'jumlah' => (int) $r['total'],
            'pcs' => (int) $r['pcs'],
        ], $rows['byStatus']);

        return [
            'subtitle' => 'Status perintah produksi yang dibuat pada rentang tanggal.',
            'note' => 'Periode dihitung dari tanggal pembuatan perintah produksi. "Lewat target" adalah perintah aktif (belum selesai/dibatalkan) dengan tanggal target terlewati.',
            'kpis' => [
                $this->kpi('Perintah produksi', $this->int($total)),
                $this->kpi('Berjalan', $this->int($countBy[ProductionOrder::STATUS_RUNNING] ?? 0)),
                $this->kpi('Selesai', $this->int($countBy[ProductionOrder::STATUS_COMPLETED] ?? 0)),
                $this->kpi('Lewat target', $this->int($rows['overdue']['total']), $this->int($rows['overdue']['pcs']) . ' pcs terdampak'),
            ],
            'sections' => [
                [
                    'caption' => 'Rekap per status',
                    'columns' => [
                        ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
                        ['key' => 'jumlah', 'label' => 'Perintah', 'type' => 'int', 'align' => 'right'],
                        ['key' => 'pcs', 'label' => 'Target (pcs)', 'type' => 'int', 'align' => 'right'],
                    ],
                    'rows' => $statusRows,
                    'empty' => 'Belum ada perintah produksi dibuat pada periode ini.',
                    'chart' => ['label' => 'status', 'value' => 'jumlah', 'type' => 'int'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function materialUsage(ReportPeriod $p): array
    {
        $rows = $this->reports->materialUsage($p);

        $usageRows = array_map(static fn(array $r): array => [
            'code' => (string) $r['code'],
            'name' => (string) $r['name'],
            'used' => (string) $r['used'],
            'unit' => (string) $r['unit'],
        ], $rows['byMaterial']);

        return [
            'subtitle' => 'Bahan yang keluar (dipakai) pada rentang tanggal, dikelompokkan per jenis.',
            'note' => 'Bersumber dari ledger stok (mutasi arah "keluar"). Unit mengikuti satuan masing-masing bahan.',
            'kpis' => [
                $this->kpi('Jenis bahan terpakai', $this->int(count($rows['byMaterial']))),
                $this->kpi('Catatan mutasi keluar', $this->int($rows['movementCount'])),
            ],
            'sections' => [
                [
                    'caption' => 'Pemakaian per bahan',
                    'columns' => [
                        ['key' => 'code', 'label' => 'Kode', 'type' => 'text'],
                        ['key' => 'name', 'label' => 'Bahan', 'type' => 'text'],
                        ['key' => 'used', 'label' => 'Dipakai', 'type' => 'num', 'align' => 'right'],
                        ['key' => 'unit', 'label' => 'Satuan', 'type' => 'text'],
                    ],
                    'rows' => $usageRows,
                    'empty' => 'Tidak ada bahan keluar pada periode ini.',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function qc(ReportPeriod $p): array
    {
        $rows = $this->reports->qc($p);
        $o = $rows['overall'];
        $rate = $o['inspected'] > 0 ? round(($o['passed'] / $o['inspected']) * 100, 1) : 0.0;

        $resultRows = array_map(static fn(array $r): array => [
            'result' => QcInspection::labelFor((string) $r['result']),
            'checks' => (int) $r['checks'],
            'inspected' => (int) $r['inspected'],
        ], $rows['byResult']);

        return [
            'subtitle' => 'Hasil pemeriksaan quality control pada rentang tanggal.',
            'note' => 'Tingkat lulus = barang lulus / barang diperiksa pada periode. Pemeriksaan "perlu perbaikan" dihitung terpisah dari lulus/gagal.',
            'kpis' => [
                $this->kpi('Pemeriksaan', $this->int($o['checks'])),
                $this->kpi('Barang diperiksa', $this->int($o['inspected'])),
                $this->kpi('Barang lulus', $this->int($o['passed'])),
                $this->kpi('Tingkat lulus', rtrim(rtrim(number_format($rate, 1, ',', ''), '0'), ',') . '%'),
            ],
            'sections' => [
                [
                    'caption' => 'Rincian per hasil',
                    'columns' => [
                        ['key' => 'result', 'label' => 'Hasil', 'type' => 'text'],
                        ['key' => 'checks', 'label' => 'Pemeriksaan', 'type' => 'int', 'align' => 'right'],
                        ['key' => 'inspected', 'label' => 'Barang', 'type' => 'int', 'align' => 'right'],
                    ],
                    'rows' => $resultRows,
                    'empty' => 'Belum ada pemeriksaan QC pada periode ini.',
                    'chart' => ['label' => 'result', 'value' => 'inspected', 'type' => 'int'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function payments(ReportPeriod $p): array
    {
        $rows = $this->reports->payments($p);

        $methodRows = array_map(static fn(array $r): array => [
            'method' => Payment::methodLabelFor((string) $r['method']),
            'n' => (int) $r['n'],
            'jumlah' => (string) $r['jumlah'],
        ], $rows['byMethod']);

        $recvRows = array_map(static fn(array $r): array => [
            'order' => (string) $r['document_number'],
            'customer' => (string) $r['customer_name'],
            'tagihan' => (string) $r['grand_total'],
            'dibayar' => (string) $r['paid'],
            'sisa' => (string) $r['outstanding'],
        ], $rows['receivables']);

        return [
            'subtitle' => 'Uang masuk pada rentang tanggal, plus posisi piutang pesanan berjalan.',
            'note' => 'PENTING: "Pendapatan diterima" adalah jumlah uang yang benar-benar masuk (SUM pembayaran) — bukan nilai pesanan. Piutang/tunggakan adalah snapshot KONDISI KINI pesanan dikonfirmasi, tidak dibatasi rentang tanggal.',
            'kpis' => [
                $this->kpi('Pendapatan diterima', money($rows['received']), 'uang masuk dalam periode'),
                $this->kpi('Transaksi pembayaran', $this->int($rows['transactionCount'])),
                $this->kpi('Piutang berjalan', money($rows['receivableTotal']), 'belum lunas (snapshot kini)'),
                $this->kpi('Pesanan berpiutang', $this->int($rows['receivableCount'])),
            ],
            'sections' => [
                [
                    'caption' => 'Uang masuk per metode',
                    'columns' => [
                        ['key' => 'method', 'label' => 'Metode', 'type' => 'text'],
                        ['key' => 'n', 'label' => 'Transaksi', 'type' => 'int', 'align' => 'right'],
                        ['key' => 'jumlah', 'label' => 'Nominal', 'type' => 'money', 'align' => 'right'],
                    ],
                    'rows' => $methodRows,
                    'empty' => 'Tidak ada pembayaran masuk pada periode ini.',
                    'chart' => ['label' => 'method', 'value' => 'jumlah', 'type' => 'money'],
                ],
                [
                    'caption' => 'Tunggakan terbesar (pesanan dikonfirmasi)',
                    'columns' => [
                        ['key' => 'order', 'label' => 'No. Pesanan', 'type' => 'text'],
                        ['key' => 'customer', 'label' => 'Pelanggan', 'type' => 'text'],
                        ['key' => 'tagihan', 'label' => 'Tagihan', 'type' => 'money', 'align' => 'right'],
                        ['key' => 'dibayar', 'label' => 'Dibayar', 'type' => 'money', 'align' => 'right'],
                        ['key' => 'sisa', 'label' => 'Sisa', 'type' => 'money', 'align' => 'right'],
                    ],
                    'rows' => $recvRows,
                    'empty' => 'Tidak ada tunggakan — semua pesanan dikonfirmasi sudah lunas.',
                    'chart' => ['label' => 'order', 'value' => 'sisa', 'type' => 'money'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function deliveries(ReportPeriod $p): array
    {
        $rows = $this->reports->deliveries($p);

        $shipBy = [];
        $pcsBy = [];
        foreach ($rows['byStatus'] as $r) {
            $shipBy[(string) $r['status']] = (int) $r['shipments'];
            $pcsBy[(string) $r['status']] = (int) $r['pcs'];
        }
        $totalShipments = array_sum($shipBy);
        $totalPcs = array_sum($pcsBy);
        $terkirimPcs = ($pcsBy[Delivery::STATUS_DIKIRIM] ?? 0) + ($pcsBy[Delivery::STATUS_DITERIMA] ?? 0);

        $statusRows = array_map(static fn(array $r): array => [
            'status' => Delivery::statusLabelFor((string) $r['status']),
            'shipments' => (int) $r['shipments'],
            'pcs' => (int) $r['pcs'],
        ], $rows['byStatus']);

        return [
            'subtitle' => 'Batch pengiriman pada rentang tanggal kirim, per status.',
            'note' => 'Batch dibatalkan tidak mengirim barang; "Terkirim" menghitung pcs pada status Dikirim + Diterima.',
            'kpis' => [
                $this->kpi('Batch pengiriman', $this->int($totalShipments)),
                $this->kpi('Total pcs (periode)', $this->int($totalPcs)),
                $this->kpi('Terkirim (dikirim+diterima)', $this->int($terkirimPcs) . ' pcs'),
                $this->kpi('Dibatalkan', $this->int($shipBy[Delivery::STATUS_BATAL] ?? 0)),
            ],
            'sections' => [
                [
                    'caption' => 'Rekap per status',
                    'columns' => [
                        ['key' => 'status', 'label' => 'Status', 'type' => 'text'],
                        ['key' => 'shipments', 'label' => 'Batch', 'type' => 'int', 'align' => 'right'],
                        ['key' => 'pcs', 'label' => 'Pcs', 'type' => 'int', 'align' => 'right'],
                    ],
                    'rows' => $statusRows,
                    'empty' => 'Belum ada pengiriman pada periode ini.',
                    'chart' => ['label' => 'status', 'value' => 'pcs', 'type' => 'int'],
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Util format
    // ------------------------------------------------------------------

    /** @return array{label: string, value: string, hint: string} */
    private function kpi(string $label, string $value, string $hint = ''): array
    {
        return ['label' => $label, 'value' => $value, 'hint' => $hint];
    }

    private function int(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }
}
