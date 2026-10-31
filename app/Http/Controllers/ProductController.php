<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Policies\ProductPolicy;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Services\Auth;
use App\Services\ProductService;
use App\Support\Paginator;

/**
 * Katalog produk + varian (§8.C). Lihat untuk semua role login;
 * ubah hanya admin/manager (ProductPolicy). Foto ditangani
 * ProductPhotoController terpisah.
 */
final class ProductController
{
    private const PER_PAGE = 15;

    public function __construct(
        private readonly View $view,
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly ProductService $products,
        private readonly ProductRepository $repository,
        private readonly ProductCategoryRepository $categories,
        private readonly ProductPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->policy->viewAny($this->auth->user());

        $user = $this->auth->user();
        $search = trim((string) $request->query('q', ''));
        $categoryId = (int) $request->query('category', '0');
        $status = $this->normalizeStatus((string) $request->query('status', 'aktif'));
        $page = max(1, (int) $request->query('page', '1'));

        $probe = $this->repository->paginate($search, $categoryId, $status, 0, 1);
        $paginator = Paginator::for($probe['total'], $page, self::PER_PAGE);
        $result = $this->repository->paginate($search, $categoryId, $status, $paginator->offset(), self::PER_PAGE);

        $data = [
            'title' => 'Produk — JahitFlow',
            'pageTitle' => 'Katalog Produk',
            'pageSubtitle' => 'Produk, varian warna/ukuran, dan harga satuan.',
            'products' => $result['list'],
            'categories' => $this->categories->allWithCount(),
            'paginator' => $paginator,
            'search' => $search,
            'categoryId' => $categoryId,
            'status' => $status,
            'canManage' => $this->policy->allowsManage($user),
            'canSetStatus' => $this->policy->allowsSetStatus($user),
        ];

        if ($request->isHtmx()) {
            return Response::html($this->view->partial('products/_table', $data));
        }

        return Response::html($this->view->render('products/index', $data));
    }

    public function create(Request $request): Response
    {
        $this->policy->manage($this->auth->user());

        return Response::html($this->view->render('products/form', [
            'title' => 'Tambah Produk — JahitFlow',
            'pageTitle' => 'Tambah Produk',
            'pageSubtitle' => 'Data dasar produk katalog.',
            'product' => null,
            'categories' => $this->categories->allWithCount(),
            'formAction' => route('products.store'),
        ]));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->manage($user);

        $result = $this->products->create($this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('products.create', [], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Produk berhasil disimpan. Silakan tambahkan varian.');

        return Response::redirect(route('products.show', ['id' => $result['id']]));
    }

    public function show(Request $request): Response
    {
        $product = $this->products->findOrFail((int) $request->routeParam('id'));
        $this->policy->view($this->auth->user(), $product);

        return Response::html($this->view->render('products/show', [
            'title' => $product->name . ' — JahitFlow',
            'pageTitle' => $product->name,
            'pageSubtitle' => trim($product->categoryName . ' · ' . ($product->garmentType ?? '')),
            'product' => $product,
            'canManage' => $this->policy->allowsManage($this->auth->user()),
            'canSetStatus' => $this->policy->allowsSetStatus($this->auth->user()),
        ]));
    }

    public function edit(Request $request): Response
    {
        $product = $this->products->findOrFail((int) $request->routeParam('id'));
        $this->policy->manage($this->auth->user());

        return Response::html($this->view->render('products/form', [
            'title' => 'Edit Produk — JahitFlow',
            'pageTitle' => 'Edit Produk',
            'pageSubtitle' => $product->name,
            'product' => $product,
            'categories' => $this->categories->allWithCount(),
            'formAction' => route('products.update', ['id' => $product->id]),
        ]));
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->user();
        $product = $this->products->findOrFail((int) $request->routeParam('id'));
        $this->policy->manage($user);

        $result = $this->products->update($product->id, $this->payload($request), $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('products.edit', ['id' => $product->id], $result['errors'], $request->all());
        }

        $this->session->flash('success', 'Perubahan produk tersimpan.');

        return Response::redirect(route('products.show', ['id' => $product->id]));
    }

    public function setStatus(Request $request): Response
    {
        $user = $this->auth->user();
        $product = $this->products->findOrFail((int) $request->routeParam('id'));
        $this->policy->setStatus($user, $product);

        $active = $request->routeParam('status') === 'aktif';
        $this->products->setStatus($product->id, $active, $user?->id);

        $this->session->flash('success', $active ? 'Produk diaktifkan kembali.' : 'Produk dinonaktifkan.');

        return Response::redirect(route('products.show', ['id' => $product->id]));
    }

    // ------------------------------------------------------------------
    // Varian
    // ------------------------------------------------------------------

    public function addVariant(Request $request): Response
    {
        $user = $this->auth->user();
        $product = $this->products->findOrFail((int) $request->routeParam('id'));
        $this->policy->manage($user);

        $result = $this->products->addVariant($product->id, [
            'color' => (string) $request->input('color', ''),
            'size' => (string) $request->input('size', ''),
            'unit_price' => $request->input('unit_price', ''),
            'sku' => (string) $request->input('sku', ''),
        ], $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('products.show', ['id' => $product->id], $result['errors'], []);
        }

        $this->session->flash('success', 'Varian ditambahkan.');

        return Response::redirect(route('products.show', ['id' => $product->id]));
    }

    public function updateVariantPrice(Request $request): Response
    {
        $user = $this->auth->user();
        $variant = $this->products->findVariantOrFail((int) $request->routeParam('variantId'));
        $product = $this->products->findOrFail($variant->productId);
        $this->policy->manage($user);

        $result = $this->products->updateVariantPrice($variant->id, ['unit_price' => $request->input('unit_price', '')], $user?->id);

        if (!$result['ok']) {
            return $this->backWithErrors('products.show', ['id' => $product->id], $result['errors'], []);
        }

        $this->session->flash('success', 'Harga varian diperbarui.');

        return Response::redirect(route('products.show', ['id' => $product->id]));
    }

    public function setVariantStatus(Request $request): Response
    {
        $user = $this->auth->user();
        $variant = $this->products->findVariantOrFail((int) $request->routeParam('variantId'));
        $product = $this->products->findOrFail($variant->productId);
        $this->policy->manage($user);

        $this->products->setVariantActive($variant->id, $request->routeParam('status') === 'aktif', $user?->id);

        $this->session->flash('success', $request->routeParam('status') === 'aktif' ? 'Varian diaktifkan.' : 'Varian dinonaktifkan.');

        return Response::redirect(route('products.show', ['id' => $product->id]));
    }

    // ------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        return [
            'category_id' => (string) $request->input('category_id', ''),
            'name' => (string) $request->input('name', ''),
            'garment_type' => (string) $request->input('garment_type', ''),
            'description' => (string) $request->input('description', ''),
            'material_spec' => (string) $request->input('material_spec', ''),
            'custom_spec' => (string) $request->input('custom_spec', ''),
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
        return in_array($status, ['aktif', 'nonaktif', 'semua'], true) ? $status : 'aktif';
    }
}
