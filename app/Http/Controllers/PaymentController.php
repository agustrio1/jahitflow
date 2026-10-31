<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Payment;
use App\Policies\PaymentPolicy;
use App\Repositories\PaymentRepository;
use App\Repositories\SalesOrderRepository;
use App\Services\Auth;
use App\Services\PaymentService;
use App\Support\Paginator;

/**
 * Pembayaran (§8.H). Controller tipis: policy → service → view.
 * Daftar riwayat, detail satu transaksi, dan form pencatatan (log — tanpa edit).
 */
final class PaymentController
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly PaymentService $payments,
        private readonly PaymentRepository $repository,
        private readonly SalesOrderRepository $orders,
        private readonly PaymentPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->viewAny($user);

        $search = trim((string) $request->query('q', ''));
        $method = $this->normalizeChoice((string) $request->query('metode', 'semua'), Payment::METHODS);
        $phase = $this->normalizeChoice((string) $request->query('jenis', 'semua'), Payment::PHASES);
        $salesOrderId = max(0, (int) $request->query('pesanan', '0'));
        $page = max(1, (int) $request->query('page', '1'));

        $probe = $this->repository->paginate($search, $method, $phase, $salesOrderId, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);
        $rows = $this->repository->paginate($search, $method, $phase, $salesOrderId, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Pembayaran — JahitFlow',
            'pageTitle' => 'Pembayaran',
            'pageSubtitle' => 'Uang muka, cicilan, dan pelunasan. Sumber "sudah diterima" = total pembayaran tercatat.',
            'payments' => $rows['list'],
            'paginator' => $paginator,
            'search' => $search,
            'method' => $method,
            'phase' => $phase,
            'salesOrderId' => $salesOrderId,
            'payableOptions' => $this->orders->selectableForPayment(),
            'canRecord' => $this->policy->allowsRecord($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('payments/_table', $data));
        }

        return Response::html($this->view->render('payments/index', $data));
    }

    public function show(Request $request): Response
    {
        $this->policy->view($this->auth->user());
        $payment = $this->payments->findOrFail((int) $request->routeParam('id'));

        return Response::html($this->view->render('payments/show', [
            'title' => 'Pembayaran ' . $payment->documentNumber . ' — JahitFlow',
            'pageTitle' => 'Transaksi Pembayaran',
            'pageSubtitle' => $payment->salesOrderNumber ?? ('Pesanan #' . $payment->salesOrderId),
            'payment' => $payment,
            'summary' => $this->payments->summaryFor($payment->salesOrderId),
        ]));
    }

    public function create(Request $request): Response
    {
        $this->policy->create($this->auth->user());

        $selectedOrderId = max(0, (int) $request->query('pesanan', '0'));

        return Response::html($this->view->render('payments/form', [
            'title' => 'Catat Pembayaran — JahitFlow',
            'pageTitle' => 'Catat Pembayaran',
            'pageSubtitle' => 'Pilih pesanan, isi jumlah/jenis/metode/tanggal, lalu simpan. Jumlah tidak boleh melebihi sisa tagihan.',
            'payment' => null,
            'payableOptions' => $this->orders->selectableForPayment(),
            'selectedOrderId' => $selectedOrderId,
            'formAction' => route('payments.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $result = $this->payments->create($this->payload($request), $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
            $this->session->flashInput($this->payload($request));

            return Response::redirect(route('payments.create'));
        }

        $this->session->flash('success', 'Pembayaran tercatat.');

        return Response::redirect(route('payments.show', ['id' => $result['id']]));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        return [
            'sales_order_id' => (int) $request->input('sales_order_id', '0'),
            'amount' => (string) $request->input('amount', ''),
            'phase' => (string) $request->input('phase', Payment::PHASE_CICILAN),
            'method' => (string) $request->input('method', Payment::METHOD_TRANSFER),
            'reference' => (string) $request->input('reference', ''),
            'payment_date' => (string) $request->input('payment_date', ''),
            'note' => (string) $request->input('note', ''),
        ];
    }

    /** @param list<string> $allowed */
    private function normalizeChoice(string $value, array $allowed): string
    {
        return in_array($value, [...$allowed, 'semua'], true) ? $value : 'semua';
    }
}
