<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Policies\CustomerPolicy;
use App\Repositories\CustomerRepository;
use App\Services\Auth;
use App\Services\CustomerService;
use App\Support\Paginator;

/**
 * Modul pelanggan (§7.1). Controller tipis: policy → service → view.
 * Daftar + pencarian lewat HTMX memakai partial `_table`; form biasa
 * (POST penuh) karena validasi & redirect-after-post lebih andal.
 */
final class CustomerController
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly CustomerService $customers,
        private readonly CustomerRepository $repository,
        private readonly CustomerPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->viewAny($user);

        $search = trim((string) $request->query('q', ''));
        $status = $this->normalizeStatus((string) $request->query('status', 'aktif'));
        $page = max(1, (int) $request->query('page', '1'));

        // paginate() mengembalikan list + total sekaligus (satu query COUNT).
        $probe = $this->repository->paginate($search, $status, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);

        $result = $this->repository->paginate($search, $status, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Pelanggan — JahitFlow',
            'pageTitle' => 'Pelanggan',
            'pageSubtitle' => 'Data pelanggan konveksi (aktif dan nonaktif).',
            'customers' => $result['list'],
            'paginator' => $paginator,
            'search' => $search,
            'status' => $status,
            'canSetStatus' => $this->policy->allowsSetStatus($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('customers/_table', $data));
        }

        return Response::html($this->view->render('customers/index', $data));
    }

    public function create(Request $request): Response
    {
        $this->policy->create($this->auth->user());

        return Response::html($this->view->render('customers/form', [
            'title' => 'Tambah Pelanggan — JahitFlow',
            'pageTitle' => 'Tambah Pelanggan',
            'pageSubtitle' => 'Isi data pelanggan baru.',
            'customer' => null,
            'formAction' => route('customers.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->create($user);

        $result = $this->customers->create($this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('customers.create', [], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Pelanggan berhasil disimpan.');

        return Response::redirect(route('customers.show', ['id' => $result['id']]));
    }

    public function show(Request $request): Response
    {
        $customer = $this->customers->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $customer);

        return Response::html($this->view->render('customers/show', [
            'title' => $customer->name . ' — JahitFlow',
            'pageTitle' => $customer->name,
            'pageSubtitle' => 'Detail pelanggan.',
            'customer' => $customer,
            'canSetStatus' => $this->policy->allowsSetStatus($this->auth->user()),
        ]));
    }

    public function edit(Request $request): Response
    {
        $customer = $this->customers->findOrFail((int) $request->routeParam('id'));
        $this->policy->update($this->auth->user(), $customer);

        return Response::html($this->view->render('customers/form', [
            'title' => 'Edit Pelanggan — JahitFlow',
            'pageTitle' => 'Edit Pelanggan',
            'pageSubtitle' => $customer->name,
            'customer' => $customer,
            'formAction' => route('customers.update', ['id' => $customer->id]),
        ]));
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->user();
        $customer = $this->customers->findOrFail((int) $request->routeParam('id'));
        $this->policy->update($user, $customer);

        $result = $this->customers->update($customer->id, $this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('customers.edit', ['id' => $customer->id], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Perubahan pelanggan tersimpan.');

        return Response::redirect(route('customers.show', ['id' => $customer->id]));
    }

    /** Aktifkan/nonaktifkan pelanggan — admin saja (policy di service route). */
    public function setStatus(Request $request): Response
    {
        $user = $this->auth->user();
        $customer = $this->customers->findOrFail((int) $request->routeParam('id'));
        $this->policy->setStatus($user, $customer);

        $active = $request->routeParam('status') === 'aktif';
        $this->customers->setStatus($customer->id, $active, $user?->id);

        $this->session->flash('success', $active ? 'Pelanggan diaktifkan kembali.' : 'Pelanggan dinonaktifkan.');

        return Response::redirect(route('customers.show', ['id' => $customer->id]));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        return [
            'name' => (string) $request->input('name', ''),
            'type' => (string) $request->input('type', 'perorangan'),
            'contact_person' => (string) $request->input('contact_person', ''),
            'phone' => (string) $request->input('phone', ''),
            'email' => (string) $request->input('email', ''),
            'address' => (string) $request->input('address', ''),
            'city' => (string) $request->input('city', ''),
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
        $this->session->flashInput($input);

        return Response::redirect(route($routeName, $routeParams));
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['aktif', 'nonaktif', 'semua'], true) ? $status : 'aktif';
    }
}
