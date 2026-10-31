<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Policies\ProductPolicy;
use App\Repositories\ProductCategoryRepository;
use App\Services\Auth;
use App\Services\ProductCategoryService;

/**
 * Kategori produk — data master kecil, daftar penuh tanpa pagination
 * (jumlah kategori konveksi realistisnya puluhan). Write dijaga
 * ProductPolicy::manage (admin & manager).
 */
final class ProductCategoryController
{
    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly ProductCategoryService $service,
        private readonly ProductCategoryRepository $categories,
        private readonly ProductPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->viewAny($user);

        return Response::html($this->view->render('categories/index', [
            'title' => 'Kategori Produk — JahitFlow',
            'pageTitle' => 'Kategori Produk',
            'pageSubtitle' => 'Pengelompokan katalog produk.',
            'categories' => $this->categories->allWithCount(),
            'canManage' => $this->policy->allowsManage($user),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->manage($user);

        $result = $this->service->create([
            'name' => (string) $request->input('name', ''),
            'description' => (string) $request->input('description', ''),
        ], $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
            $this->session->flashInput($request->all());

            return Response::redirect(route('categories.index'));
        }

        $this->session->flash('success', 'Kategori ditambahkan.');

        return Response::redirect(route('categories.index'));
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->manage($user);

        $id = (int) $request->routeParam('id');

        $result = $this->service->update($id, [
            'name' => (string) $request->input('name', ''),
            'description' => (string) $request->input('description', ''),
        ], $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);

            return Response::redirect(route('categories.index'));
        }

        $this->session->flash('success', 'Kategori diperbarui.');

        return Response::redirect(route('categories.index'));
    }

    public function destroy(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->manage($user);

        $result = $this->service->delete((int) $request->routeParam('id'), $user?->id);

        if (!$result['ok']) {
            $this->session->flash('error', $result['message']);

            return Response::redirect(route('categories.index'));
        }

        $this->session->flash('success', $result['message']);

        return Response::redirect(route('categories.index'));
    }
}
