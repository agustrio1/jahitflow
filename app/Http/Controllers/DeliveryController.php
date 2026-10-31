<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Delivery;
use App\Policies\DeliveryPolicy;
use App\Repositories\DeliveryRepository;
use App\Repositories\SalesOrderRepository;
use App\Services\Auth;
use App\Services\DeliveryService;
use App\Support\DeliveryFlow;
use App\Support\Paginator;

/**
 * Pengiriman (§8.I). Controller tipis: policy → service → view. Daftar riwayat,
 * detail satu batch (+ tombol transisi status), dan form pembuatan batch baru.
 * Aturan jumlah & transisi dijaga DeliveryService/DeliveryFlow di server.
 */
final class DeliveryController
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly DeliveryService $deliveries,
        private readonly DeliveryRepository $repository,
        private readonly SalesOrderRepository $orders,
        private readonly DeliveryPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->viewAny($user);

        $search = trim((string) $request->query('q', ''));
        $status = $this->normalizeChoice((string) $request->query('status', 'semua'), Delivery::STATUSES);
        $method = $this->normalizeChoice((string) $request->query('metode', 'semua'), Delivery::METHODS);
        $salesOrderId = max(0, (int) $request->query('pesanan', '0'));
        $page = max(1, (int) $request->query('page', '1'));

        $probe = $this->repository->paginate($search, $status, $method, $salesOrderId, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);
        $rows = $this->repository->paginate($search, $status, $method, $salesOrderId, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Pengiriman — JahitFlow',
            'pageTitle' => 'Pengiriman',
            'pageSubtitle' => 'Batch pengiriman per pesanan. Jumlah siap-kirim dibatasi hasil QC & jumlah pesanan.',
            'deliveries' => $rows['list'],
            'paginator' => $paginator,
            'search' => $search,
            'status' => $status,
            'method' => $method,
            'salesOrderId' => $salesOrderId,
            'deliverableOptions' => $this->orders->selectableForDelivery(),
            'canCreate' => $this->policy->allowsCreate($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('deliveries/_table', $data));
        }

        return Response::html($this->view->render('deliveries/index', $data));
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->view($user);
        $delivery = $this->deliveries->findOrFail((int) $request->routeParam('id'));

        return Response::html($this->view->render('deliveries/show', [
            'title' => 'Pengiriman ' . $delivery->documentNumber . ' — JahitFlow',
            'pageTitle' => 'Data Pengiriman',
            'pageSubtitle' => $delivery->salesOrderNumber ?? ('Pesanan #' . $delivery->salesOrderId),
            'delivery' => $delivery,
            'summary' => $this->deliveries->summaryFor($delivery->salesOrderId),
            'nextStates' => DeliveryFlow::nextStates($delivery->status),
            'canManage' => $this->policy->allowsManage($user),
        ]));
    }

    public function create(Request $request): Response
    {
        $this->policy->create($this->auth->user());

        $selectedOrderId = max(0, (int) $request->query('pesanan', '0'));

        return Response::html($this->view->render('deliveries/form', [
            'title' => 'Buat Pengiriman — JahitFlow',
            'pageTitle' => 'Buat Batch Pengiriman',
            'pageSubtitle' => 'Pilih pesanan, isi jumlah/tanggal/metode. Jumlah tidak boleh melebihi barang siap kirim.',
            'delivery' => null,
            'deliverableOptions' => $this->orders->selectableForDelivery(),
            'selectedOrderId' => $selectedOrderId,
            'formAction' => route('deliveries.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $result = $this->deliveries->create($this->payload($request), $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
            $this->session->flashInput($this->payload($request));

            return Response::redirect(route('deliveries.create'));
        }

        $this->session->flash('success', 'Batch pengiriman dibuat (status: Disiapkan).');

        return Response::redirect(route('deliveries.show', ['id' => $result['id']]));
    }

    /**
     * Transisi status. Target dibaca dari body ('to'); kelayakan ditegakkan
     * DeliveryFlow di service (server-side, §9/§12) — bukan sekadar tombol disembunyi.
     */
    public function updateStatus(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->updateStatus($user);

        $id = (int) $request->routeParam('id');
        $to = (string) $request->input('to', '');

        $result = $this->deliveries->transition($id, $to, $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
        } else {
            $this->session->flash('success', 'Status pengiriman diperbarui.');
        }

        return Response::redirect(route('deliveries.show', ['id' => $id]));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        return [
            'sales_order_id' => (int) $request->input('sales_order_id', '0'),
            'quantity' => (string) $request->input('quantity', ''),
            'delivery_date' => (string) $request->input('delivery_date', ''),
            'method' => (string) $request->input('method', Delivery::METHOD_KURIR),
            'courier' => (string) $request->input('courier', ''),
            'tracking_number' => (string) $request->input('tracking_number', ''),
            'recipient' => (string) $request->input('recipient', ''),
            'handover_ref' => (string) $request->input('handover_ref', ''),
            'note' => (string) $request->input('note', ''),
        ];
    }

    /** @param list<string> $allowed */
    private function normalizeChoice(string $value, array $allowed): string
    {
        return in_array($value, [...$allowed, 'semua'], true) ? $value : 'semua';
    }
}
