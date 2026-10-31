<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\RawMaterial;
use App\Policies\RawMaterialPolicy;
use App\Repositories\RawMaterialRepository;
use App\Repositories\StockMovementRepository;
use App\Services\Auth;
use App\Services\RawMaterialService;
use App\Services\StockService;
use App\Support\Paginator;
use App\Support\StockLedger;

/**
 * Bahan baku & stok (§8.F). Controller tipis: policy → service → view.
 * CRUD master bahan + pencatatan pergerakan stok (ledger) dan riwayatnya.
 */
final class RawMaterialController
{
    private const PER_PAGE = 15;
    private const HISTORY_PER_PAGE = 20;

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly RawMaterialService $materials,
        private readonly StockService $stock,
        private readonly RawMaterialRepository $repository,
        private readonly StockMovementRepository $movements,
        private readonly RawMaterialPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->viewAny($user);

        $search = trim((string) $request->query('q', ''));
        $type = $this->normalizeType((string) $request->query('type', 'semua'));
        $status = $this->normalizeStatus((string) $request->query('status', 'aktif'));
        $lowOnly = $request->query('low') === '1';
        $page = max(1, (int) $request->query('page', '1'));

        $probe = $this->repository->paginate($search, $type, $status, $lowOnly, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);
        $result = $this->repository->paginate($search, $type, $status, $lowOnly, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Bahan Baku & Stok — JahitFlow',
            'pageTitle' => 'Bahan Baku & Stok',
            'pageSubtitle' => 'Inventaris bahan: saldo, peringatan minimum, dan penerimaan/pemakaian.',
            'materials' => $result['list'],
            'paginator' => $paginator,
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'lowOnly' => $lowOnly,
            'canManage' => $this->policy->allowsManage($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('materials/_table', $data));
        }

        return Response::html($this->view->render('materials/index', $data));
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->user();
        $material = $this->materials->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($user, $material);

        $page = max(1, (int) $request->query('page', '1'));
        $probe = $this->movements->paginateForMaterial($material->id, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::HISTORY_PER_PAGE);
        $history = $this->movements->paginateForMaterial($material->id, $paginator->offset(), self::HISTORY_PER_PAGE);

        return Response::html($this->view->render('materials/show', [
            'title' => $material->name . ' — JahitFlow',
            'pageTitle' => $material->name,
            'pageSubtitle' => $material->code . ' · ' . $material->typeLabel(),
            'material' => $material,
            'history' => $history['list'],
            'paginator' => $paginator,
            'canManage' => $this->policy->allowsManage($user),
            'canRecord' => $this->policy->allowsRecord($user),
        ]));
    }

    public function create(Request $request): Response
    {
        $this->policy->create($this->auth->user());

        return Response::html($this->view->render('materials/form', [
            'title' => 'Tambah Bahan — JahitFlow',
            'pageTitle' => 'Tambah Bahan',
            'pageSubtitle' => 'Buat master bahan. Saldo awal selalu nol — penerimaan dicatat via ledger.',
            'material' => null,
            'formAction' => route('materials.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $result = $this->materials->create($this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('materials.create', [], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Bahan berhasil dibuat. Catat penerimaan untuk mengisi stok.');

        return Response::redirect(route('materials.show', ['id' => $result['id']]));
    }

    public function edit(Request $request): Response
    {
        $material = $this->materials->findOrFail((int) $request->routeParam('id'));
        $this->policy->update($this->auth->user(), $material);

        return Response::html($this->view->render('materials/form', [
            'title' => 'Edit Bahan — JahitFlow',
            'pageTitle' => 'Edit Bahan',
            'pageSubtitle' => $material->name,
            'material' => $material,
            'formAction' => route('materials.update', ['id' => $material->id]),
        ]));
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->user();
        $material = $this->materials->findOrFail((int) $request->routeParam('id'));
        $this->policy->update($user, $material);

        $result = $this->materials->update($material->id, $this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('materials.edit', ['id' => $material->id], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Perubahan bahan tersimpan.');

        return Response::redirect(route('materials.show', ['id' => $material->id]));
    }

    public function setStatus(Request $request): Response
    {
        $user = $this->auth->user();
        $material = $this->materials->findOrFail((int) $request->routeParam('id'));
        $this->policy->setStatus($user, $material);

        $active = $request->routeParam('status') === 'aktif';
        $this->materials->setStatus($material->id, $active, $user?->id);

        $this->session->flash('success', $active ? 'Bahan diaktifkan kembali.' : 'Bahan dinonaktifkan.');

        return Response::redirect(route('materials.show', ['id' => $material->id]));
    }

    /** Catat satu pergerakan stok (arah dibaca dari input, divalidasi service). */
    public function record(Request $request): Response
    {
        $user = $this->auth->user();
        $material = $this->materials->findOrFail((int) $request->routeParam('id'));
        $this->policy->record($user);

        $direction = (string) $request->input('direction', '');
        if (!in_array($direction, [StockLedger::IN, StockLedger::OUT, StockLedger::ADJUSTMENT], true)) {
            $this->session->flash('error', 'Arah pergerakan stok tidak valid.');

            return Response::redirect(route('materials.show', ['id' => $material->id]));
        }

        $result = $this->stock->record($material->id, $direction, $this->movementForm($request), $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
            $this->session->flashInput(['direction' => $direction] + $this->movementForm($request));

            return Response::redirect(route('materials.show', ['id' => $material->id]));
        }

        $this->session->flash('success', 'Pergerakan stok tercatat. Saldo kini: ' . RawMaterial::formatQty((string) $result['balance_after']) . ' ' . $material->unit . '.');

        return Response::redirect(route('materials.show', ['id' => $material->id]));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        return [
            'code' => (string) $request->input('code', ''),
            'name' => (string) $request->input('name', ''),
            'type' => (string) $request->input('type', RawMaterial::TYPE_UTAMA),
            'unit' => (string) $request->input('unit', ''),
            'min_stock' => (string) $request->input('min_stock', '0'),
            'notes' => (string) $request->input('notes', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function movementForm(Request $request): array
    {
        return [
            'quantity' => (string) $request->input('quantity', ''),
            'reason' => (string) $request->input('reason', ''),
            'reference_type' => (string) $request->input('reference_type', ''),
            'reference_id' => (string) $request->input('reference_id', ''),
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
        $this->session->flashInput($input);

        return Response::redirect(route($routeName, $routeParams));
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['aktif', 'nonaktif', 'semua'], true) ? $status : 'aktif';
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, [...RawMaterial::TYPES, 'semua'], true) ? $type : 'semua';
    }
}
