<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Exceptions\HttpException;
use App\Policies\ProductPolicy;
use App\Services\Auth;
use App\Services\ProductPhotoService;

/**
 * Foto referensi produk. File disimpan PRIVAT di luar webroot, jadi
 * setiap tampilan gambar lewat route ini ( bersyarat login ) dan
 * dialirkan dengan readfile, bukan link langsung.
 */
final class ProductPhotoController
{
    public function __construct(
        private readonly Session $session,
        private readonly Auth $auth,
        private readonly ProductPhotoService $photos,
        private readonly ProductPolicy $policy,
    ) {
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->manage($user);

        $productId = (int) $request->routeParam('id');

        $result = $this->photos->store($productId, $request->file('photo'), $user?->id);

        if (!$result['ok']) {
            $this->session->flashErrors($result['errors']);
        } else {
            $this->session->flash('success', 'Foto terunggah.');
        }

        return Response::redirect(route('products.show', ['id' => $productId]));
    }

    public function destroy(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->manage($user);

        $photoId = (int) $request->routeParam('photoId');
        $row = $this->photos->photoRow($photoId);
        if ($row === null) {
            throw new HttpException(404, 'Foto tidak ditemukan.');
        }

        $this->photos->delete($photoId, $user?->id);
        $this->session->flash('success', 'Foto dihapus.');

        return Response::redirect(route('products.show', ['id' => (int) $row['product_id']]));
    }

    /**
     * Streaming gambar ke <img>. Foto bukan data sensitif (hanya
     * referensi produk), cukup dijaga login (viewAny).
     */
    public function show(Request $request): Response
    {
        $this->policy->viewAny($this->auth->user());

        $photoId = (int) $request->routeParam('photoId');
        $row = $this->photos->photoRow($photoId);
        if ($row === null) {
            throw new HttpException(404, 'Foto tidak ditemukan.');
        }

        $file = $this->photos->readForStreaming($row);

        // Foto dibatasi ≤2 MB di service, jadi aman dimuat penuh ke body;
        // header (termasuk Content-Type asli) dikirim Response::send().
        $binary = file_get_contents($file['path']);
        if ($binary === false) {
            throw new HttpException(404, 'Berkas foto tidak dapat dibaca.');
        }

        return new Response($binary, 200, [
            'Content-Type' => $file['mime'],
            'Content-Length' => (string) $file['size'],
            'Content-Disposition' => 'inline; filename="photo-' . $photoId . '"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
