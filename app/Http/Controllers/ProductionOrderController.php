<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\ProductionOrder;
use App\Policies\ProductionOrderPolicy;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\SalesOrderRepository;
use App\Services\Auth;
use App\Services\ProductionService;
use App\Support\Csv;
use App\Support\Paginator;

/**
 * Perintah produksi (§8.E). Controller tipis: policy → service → view.
 * Pembuatan dari pesanan, penomoran, snapshot tahap, dan transisi progres
 * dijaga ProductionService + ProductionStageMachine di server.
 */
final class ProductionOrderController
{
    private const PER_PAGE = 15;
    private const STAGE_ACTIONS = ['start', 'complete', 'reopen', 'update'];

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly ProductionService $production,
        private readonly ProductionOrderRepository $repository,
        private readonly SalesOrderRepository $salesOrders,
        private readonly ProductionOrderPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->policy->viewAny($this->auth->user());

        $user = $this->auth->user();
        $search = trim((string) $request->query('q', ''));
        $status = $this->normalizeStatus((string) $request->query('status', 'semua'));
        $assignedTo = (int) $request->query('pic', '0');
        $page = max(1, (int) $request->query('page', '1'));

        $probe = $this->repository->paginate($search, $status, $assignedTo, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);
        $result = $this->repository->paginate($search, $status, $assignedTo, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Produksi — JahitFlow',
            'pageTitle' => 'Production Order',
            'pageSubtitle' => 'Perintah produksi dari pesanan terkonfirmasi, dengan papan tahap.',
            'orders' => $result['list'],
            'paginator' => $paginator,
            'search' => $search,
            'status' => $status,
            'pic' => $assignedTo,
            'pics' => $this->repository->assignableUsers(),
            'canManage' => $this->policy->allowsManage($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('production-orders/_table', $data));
        }

        return Response::html($this->view->render('production-orders/index', $data));
    }

    public function show(Request $request): Response
    {
        $order = $this->production->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $order);

        return Response::html($this->view->render('production-orders/show', [
            'title' => 'Produksi ' . $order->documentNumber . ' — JahitFlow',
            'pageTitle' => $order->documentNumber,
            'pageSubtitle' => $order->customerName ?? '',
            'order' => $order,
            'pics' => $this->repository->assignableUsers(),
            'canManage' => $this->policy->allowsManage($this->auth->user()),
            'canProgress' => $this->policy->allowsProgress($this->auth->user()),
        ]));
    }

    /** Unduhan CSV satu perintah produksi (digates policy view). */
    public function unduhCsv(Request $request): Response
    {
        $order = $this->production->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $order);

        $meta = [
            'Nomor Dokumen'    => $order->documentNumber,
            'Status'           => $order->statusLabel(),
            'Pesanan Sumber'   => $order->salesOrderNumber ?? '',
            'Pelanggan'        => $order->customerName ?? '',
            'Target Kuantitas' => $order->targetQuantity,
            'Tanggal Target'   => $order->targetDate,
            'Penanggung Jawab' => $order->assignedUserName ?? '',
        ];

        $columns = [
            ['key' => 'posisi',    'label' => 'Posisi'],
            ['key' => 'tahap',     'label' => 'Tahap'],
            ['key' => 'status',    'label' => 'Status'],
            ['key' => 'target',    'label' => 'Target (pcs)'],
            ['key' => 'realisasi', 'label' => 'Realisasi (pcs)'],
            ['key' => 'pic',       'label' => 'PIC'],
            ['key' => 'mulai',     'label' => 'Mulai'],
            ['key' => 'selesai',   'label' => 'Selesai'],
            ['key' => 'catatan',   'label' => 'Catatan'],
        ];

        $rows = [];
        foreach ($order->stages as $stage) {
            $rows[] = [
                'posisi'    => $stage->position,
                'tahap'     => $stage->stageName,
                'status'    => $stage->statusLabel(),
                'target'    => $stage->targetQty,
                'realisasi' => $stage->realizedQty,
                'pic'       => $stage->assignedUserName ?? '',
                'mulai'     => $stage->startedAt ?? '',
                'selesai'   => $stage->finishedAt ?? '',
                'catatan'   => $stage->notes ?? '',
            ];
        }

        return Response::csv(Csv::document($meta, 'Tahap Produksi', $columns, $rows), 'PP-' . $order->documentNumber . '.csv');
    }

    /**
     * Surat Production Order siap-cetak (HTML; simpan-PDF via browser).
     * Dokumen mandiri, tanpa library. Digates policy view.
     */
    public function unduhPdf(Request $request): Response
    {
        $order = $this->production->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $order);

        return Response::html($this->view->render('production-orders/letter', ['order' => $order]));
    }

    /**
     * Form pembuatan perintah produksi dari pesanan. Bila perintah sudah ada,
     * diarahkan ke detailnya (tidak duplikat — §9).
     */
    public function create(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $salesOrderId = (int) $request->query('pesanan', '0');
        $so = $this->salesOrders->find($salesOrderId);
        if ($so === null) {
            $this->session->flash('error', 'Pesanan sumber tidak ditemukan.');

            return Response::redirect(route('sales_orders.index'));
        }

        $existing = $this->repository->findBySalesOrder($so->id);
        if ($existing !== null) {
            $this->session->flash('info', 'Perintah produksi untuk pesanan ini sudah dibuat.');

            return Response::redirect(route('produksi.show', ['id' => $existing->id]));
        }

        return Response::html($this->view->render('production-orders/create', [
            'title' => 'Perintah Produksi Baru — JahitFlow',
            'pageTitle' => 'Buat Perintah Produksi',
            'pageSubtitle' => $so->documentNumber . ' · ' . ($so->customerName ?? ''),
            'salesOrder' => $so,
            'pics' => $this->repository->assignableUsers(),
            'formAction' => route('produksi.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $salesOrderId = (int) $request->input('sales_order_id', '0');
        $result = $this->production->createFromSalesOrder($salesOrderId, $this->createForm($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('produksi.create', ['pesanan' => $salesOrderId], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Perintah produksi dibuat. Tahap telah di-snapshot dari konfigurasi.');

        return Response::redirect(route('produksi.show', ['id' => $result['id']]));
    }

    /**
     * Transisi/penyimpanan satu tahap. Aksi menentukan aturan mesin:
     * start/complete/reopen mengubah status; update hanya menyimpan progres.
     */
    public function updateStage(Request $request): Response
    {
        $user = $this->auth->user();

        $stageId = (int) $request->routeParam('stageId');
        $action = (string) $request->routeParam('action');
        if (!in_array($action, self::STAGE_ACTIONS, true)) {
            $this->session->flash('error', 'Aksi tahap tidak dikenal.');

            return Response::redirect(route('produksi.index'));
        }

        $stage = $this->repository->findStage($stageId);
        if ($stage === null) {
            throw new \App\Core\Exceptions\HttpException(404, 'Tahap produksi tidak ditemukan.');
        }

        $order = $this->production->findOrFail($stage->productionOrderId);
        $this->policy->updateStage($user, $order);

        $result = $this->production->updateStage($stageId, $action, $this->stageForm($request), $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
        } else {
            $this->session->flash('success', 'Progres tahap diperbarui.');
        }

        return Response::redirect(route('produksi.show', ['id' => $order->id]));
    }

    public function cancel(Request $request): Response
    {
        $user = $this->auth->user();
        $order = $this->production->findOrFail((int) $request->routeParam('id'));
        $this->policy->cancel($user, $order);

        $this->production->cancel($order->id, $user?->id);
        $this->session->flash('success', 'Perintah produksi dibatalkan.');

        return Response::redirect(route('produksi.show', ['id' => $order->id]));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function createForm(Request $request): array
    {
        return [
            'assigned_to' => (string) $request->input('assigned_to', ''),
            'target_date' => (string) $request->input('target_date', ''),
            'description' => (string) $request->input('description', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function stageForm(Request $request): array
    {
        return [
            'target_qty' => (string) $request->input('target_qty', ''),
            'realized_qty' => (string) $request->input('realized_qty', ''),
            'assigned_to' => (string) $request->input('assigned_to', ''),
            'notes' => (string) $request->input('notes', ''),
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
        $allowed = [
            ProductionOrder::STATUS_QUEUED,
            ProductionOrder::STATUS_RUNNING,
            ProductionOrder::STATUS_COMPLETED,
            ProductionOrder::STATUS_CANCELLED,
            'semua',
        ];

        return in_array($status, $allowed, true) ? $status : 'semua';
    }
}
