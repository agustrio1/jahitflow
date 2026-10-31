<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\SalesOrder;
use App\Policies\SalesOrderPolicy;
use App\Repositories\CustomerRepository;
use App\Repositories\SalesOrderRepository;
use App\Services\Auth;
use App\Services\SalesOrderService;
use App\Support\Csv;
use App\Support\Paginator;

/**
 * Sales order (§8.D). Controller tipis: policy → service → view.
 * Perhitungan total, penomoran, dan transisi status hidup di service.
 */
final class SalesOrderController
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly SalesOrderService $orders,
        private readonly SalesOrderRepository $repository,
        private readonly CustomerRepository $customers,
        private readonly SalesOrderPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->policy->viewAny($this->auth->user());

        $user = $this->auth->user();
        $search = trim((string) $request->query('q', ''));
        $status = $this->normalizeStatus((string) $request->query('status', 'semua'));
        $page = max(1, (int) $request->query('page', '1'));

        $probe = $this->repository->paginate($search, $status, 0, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);
        $result = $this->repository->paginate($search, $status, 0, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Pesanan — JahitFlow',
            'pageTitle' => 'Sales Order',
            'pageSubtitle' => 'Pesanan konveksi dari penawaran hingga dikonfirmasi.',
            'orders' => $result['list'],
            'paginator' => $paginator,
            'search' => $search,
            'status' => $status,
            'canManage' => $this->policy->allowsManage($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('sales-orders/_table', $data));
        }

        return Response::html($this->view->render('sales-orders/index', $data));
    }

    public function show(Request $request): Response
    {
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $order);

        return Response::html($this->view->render('sales-orders/show', [
            'title' => 'Pesanan ' . $order->documentNumber . ' — JahitFlow',
            'pageTitle' => $order->documentNumber,
            'pageSubtitle' => $order->customerName ?? '',
            'order' => $order,
            'canManage' => $this->policy->allowsManage($this->auth->user()),
        ]));
    }

    /** Unduhan CSV satu pesanan (baca: siapa pun yang boleh melihat boleh mengekspor). */
    public function unduhCsv(Request $request): Response
    {
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $order);

        $meta = [
            'Nomor Dokumen'   => $order->documentNumber,
            'Status'          => $order->statusLabel(),
            'Pelanggan'       => $order->customerName ?? '',
            'Tanggal Pesanan' => $order->orderDate,
            'Tenggat'         => $order->dueDate,
            'Subtotal'        => $order->subtotal,
            'Diskon'          => $order->discountAmount,
            'Biaya Tambahan'  => $order->additionalCost,
            'Total'           => $order->grandTotal,
            'Uang Muka'       => $order->dpAmount,
            'Sisa Pembayaran' => $order->remaining(),
        ];

        $columns = [
            ['key' => 'produk',   'label' => 'Produk'],
            ['key' => 'varian',   'label' => 'Varian'],
            ['key' => 'jumlah',   'label' => 'Jumlah'],
            ['key' => 'harga',    'label' => 'Harga Satuan'],
            ['key' => 'subtotal', 'label' => 'Subtotal'],
        ];

        $rows = [];
        foreach ($order->items as $item) {
            $rows[] = [
                'produk'   => $item->productName,
                'varian'   => $item->variantLabel(),
                'jumlah'   => $item->quantity,
                'harga'    => $item->unitPrice,
                'subtotal' => $item->lineTotal,
            ];
        }

        return Response::csv(Csv::document($meta, 'Item Pesanan', $columns, $rows), 'SO-' . $order->documentNumber . '.csv');
    }

    /**
     * Surat Sales Order siap-cetak (HTML; simpan-PDF via browser). Bukan
     * PDF biner — tanpa library, sesuai batasan DOC. Digates policy view.
     */
    public function unduhPdf(Request $request): Response
    {
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $order);

        return Response::html($this->view->render('sales-orders/letter', ['order' => $order]));
    }

    public function create(Request $request): Response
    {
        $this->policy->create($this->auth->user());

        return Response::html($this->view->render('sales-orders/form', [
            'title' => 'Pesanan Baru — JahitFlow',
            'pageTitle' => 'Buat Pesanan',
            'pageSubtitle' => 'Draft; total dihitung otomatis dari harga varian di sistem.',
            'order' => null,
            'customers' => $this->customers->allActive(),
            'variantOptions' => $this->repository->activeVariantOptions(),
            'formAction' => route('sales_orders.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $result = $this->orders->create($this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('sales_orders.create', [], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Pesanan berhasil dibuat sebagai draft.');

        return Response::redirect(route('sales_orders.show', ['id' => $result['id']]));
    }

    public function edit(Request $request): Response
    {
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->update($this->auth->user(), $order);

        return Response::html($this->view->render('sales-orders/form', [
            'title' => 'Edit Pesanan — JahitFlow',
            'pageTitle' => 'Edit Pesanan',
            'pageSubtitle' => $order->documentNumber,
            'order' => $order,
            'customers' => $this->customers->allActive(),
            'variantOptions' => $this->repository->activeVariantOptions(),
            'formAction' => route('sales_orders.update', ['id' => $order->id]),
        ]));
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->user();
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->update($user, $order);

        $result = $this->orders->update($order->id, $this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('sales_orders.edit', ['id' => $order->id], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Perubahan pesanan tersimpan.');

        return Response::redirect(route('sales_orders.show', ['id' => $order->id]));
    }

    // ------------------------------------------------------------------
    // Transisi status

    public function offer(Request $request): Response
    {
        $user = $this->auth->user();
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->update($user, $order);

        $this->orders->markOffered($order->id, $user?->id);
        $this->session->flash('success', 'Pesanan ditandai sebagai penawaran.');

        return Response::redirect(route('sales_orders.show', ['id' => $order->id]));
    }

    public function confirm(Request $request): Response
    {
        $user = $this->auth->user();
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->confirm($user, $order);

        $result = $this->orders->confirm($order->id, $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('sales_orders.show', ['id' => $order->id], $result['errors'], []);
        }

        $this->session->flash('success', 'Pesanan dikonfirmasi. Gerbang produksi: DP harus memenuhi ketentuan.');

        return Response::redirect(route('sales_orders.show', ['id' => $order->id]));
    }

    public function cancel(Request $request): Response
    {
        $user = $this->auth->user();
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->cancel($user, $order);

        $this->orders->cancel($order->id, $user?->id);
        $this->session->flash('success', 'Pesanan dibatalkan.');

        return Response::redirect(route('sales_orders.show', ['id' => $order->id]));
    }

    public function destroy(Request $request): Response
    {
        $user = $this->auth->user();
        $order = $this->orders->findOrFail((int) $request->routeParam('id'));
        $this->policy->delete($user, $order);

        $this->orders->delete($order->id, $user?->id);
        $this->session->flash('success', 'Draft pesanan dihapus.');

        return Response::redirect(route('sales_orders.index'));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $raw = $request->input('items', []);
        $items = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $variantId = trim((string) ($row['variant_id'] ?? ''));
                $quantity = trim((string) ($row['quantity'] ?? ''));
                // Buang baris kosong sepenuhnya (sisa template Alpine).
                if ($variantId === '' && $quantity === '') {
                    continue;
                }
                $items[] = ['variant_id' => $variantId, 'quantity' => $quantity];
            }
        }

        return [
            'customer_id' => (string) $request->input('customer_id', ''),
            'order_date' => (string) $request->input('order_date', ''),
            'due_date' => (string) $request->input('due_date', ''),
            'discount_amount' => (string) $request->input('discount_amount', '0'),
            'additional_cost' => (string) $request->input('additional_cost', '0'),
            'dp_amount' => (string) $request->input('dp_amount', '0'),
            'design_notes' => (string) $request->input('design_notes', ''),
            'items' => $items,
        ];
    }

    /**
     * @param array<string, list<string>> $errors
     * @param array<string, string>       $routeParams
     * @param array<string, mixed>        $input
     */
    private function backWithErrors(string $routeName, array $routeParams, array $errors, array $input): Response
    {
        $this->session->flashErrors($errors);

        if ($input !== []) {
            $this->session->flashInput($input);
        }

        return Response::redirect(route($routeName, $routeParams));
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = [...SalesOrder::STATUSES, 'semua'];

        return in_array($status, $allowed, true) ? $status : 'semua';
    }
}
