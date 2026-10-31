<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\QcInspection;
use App\Policies\QcPolicy;
use App\Repositories\ProductionOrderRepository;
use App\Repositories\QcInspectionRepository;
use App\Services\Auth;
use App\Services\QcService;
use App\Support\Paginator;

/**
 * Quality Control (§8.G). Controller tipis: policy → service → view.
 * Daftar riwayat, detail satu pemeriksaan, dan form pencatatan (log — tanpa edit).
 */
final class QcController
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly QcService $qc,
        private readonly QcInspectionRepository $repository,
        private readonly ProductionOrderRepository $orders,
        private readonly QcPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->viewAny($user);

        $search = trim((string) $request->query('q', ''));
        $result = $this->normalizeResult((string) $request->query('result', 'semua'));
        $productionOrderId = max(0, (int) $request->query('produksi', '0'));
        $page = max(1, (int) $request->query('page', '1'));

        $probe = $this->repository->paginate($search, $result, $productionOrderId, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);
        $rows = $this->repository->paginate($search, $result, $productionOrderId, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Quality Control — JahitFlow',
            'pageTitle' => 'Quality Control',
            'pageSubtitle' => 'Pemeriksaan hasil produksi: jumlah lolos/gagal, catatan cacat, dan tindakan perbaikan.',
            'inspections' => $rows['list'],
            'paginator' => $paginator,
            'search' => $search,
            'result' => $result,
            'productionOrderId' => $productionOrderId,
            'producibleOptions' => $this->orders->selectableForQc(),
            'canRecord' => $this->policy->allowsRecord($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('qc/_table', $data));
        }

        return Response::html($this->view->render('qc/index', $data));
    }

    public function show(Request $request): Response
    {
        $this->policy->view($this->auth->user());
        $inspection = $this->qc->findOrFail((int) $request->routeParam('id'));

        return Response::html($this->view->render('qc/show', [
            'title' => 'Pemeriksaan QC #' . $inspection->id . ' — JahitFlow',
            'pageTitle' => 'Pemeriksaan QC',
            'pageSubtitle' => $inspection->productionOrderNumber ?? ('Perintah #' . $inspection->productionOrderId),
            'inspection' => $inspection,
        ]));
    }

    public function create(Request $request): Response
    {
        $this->policy->create($this->auth->user());

        return Response::html($this->view->render('qc/form', [
            'title' => 'Catat Pemeriksaan QC — JahitFlow',
            'pageTitle' => 'Catat Pemeriksaan QC',
            'pageSubtitle' => 'Pilih perintah produksi, isi jumlah diperiksa/lolos/gagal, lalu simpan.',
            'inspection' => null,
            'producibleOptions' => $this->orders->selectableForQc(),
            'formAction' => route('qc.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $result = $this->qc->create($this->payload($request), $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
            $this->session->flashInput($this->payload($request));

            return Response::redirect(route('qc.create'));
        }

        $this->session->flash('success', 'Pemeriksaan QC tercatat.');

        return Response::redirect(route('qc.show', ['id' => $result['id']]));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        return [
            'production_order_id' => (int) $request->input('production_order_id', '0'),
            'inspected_qty' => (string) $request->input('inspected_qty', ''),
            'passed_qty' => (string) $request->input('passed_qty', ''),
            'failed_qty' => (string) $request->input('failed_qty', ''),
            'result' => (string) $request->input('result', QcInspection::RESULT_LOLOS),
            'defect_note' => (string) $request->input('defect_note', ''),
            'rework_action' => (string) $request->input('rework_action', ''),
        ];
    }

    private function normalizeResult(string $result): string
    {
        return in_array($result, [...QcInspection::RESULTS, 'semua'], true) ? $result : 'semua';
    }
}
