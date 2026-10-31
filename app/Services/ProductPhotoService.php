<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Repositories\AuditRepository;
use App\Repositories\ProductRepository;

/**
 * Foto referensi produk (§12 DOC: file privat di luar webroot).
 * Validasi upload server-side penuh: errno, ukuran, MIME via finfo
 * (bukan ekstensi — browser bisa memalsukan nama file), nama file
 * acak agar tidak tertebak. Serving lewat route bersyarat login,
 * bukan URL publik.
 */
final class ProductPhotoService
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    /** mime => ekstensi aman yang boleh dipakai nama file. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const DIR = 'storage/uploads/products';

    public function __construct(
        private readonly Database $db,
        private readonly ProductRepository $products,
        private readonly AuditRepository $audit,
    ) {
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $file entry $_FILES
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function store(int $productId, ?array $file, ?int $userId): array
    {
        $product = $this->products->find($productId);
        if ($product === null) {
            throw new HttpException(404, 'Produk tidak ditemukan.');
        }

        $errors = $this->inspectUpload($file);
        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        /** @var array{name: string, tmp_name: string, size: int} $file */
        $mime = $this->detectMime($file['tmp_name']);
        $extension = self::ALLOWED[$mime];
        $relativePath = self::DIR . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $target = BASE_PATH . '/' . $relativePath;

        $this->ensureDirectory(dirname($target));
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return ['ok' => false, 'id' => null, 'errors' => ['photo' => ['Gagal menyimpan file di server. Coba lagi.']]];
        }

        try {
            $id = $this->db->transaction(function () use ($productId, $relativePath, $file, $mime, $userId, $product): int {
                $newId = $this->products->addPhoto(
                    $productId,
                    $relativePath,
                    mb_substr($file['name'], 0, 255),
                    $mime,
                    (int) $file['size'],
                    $userId,
                );
                $this->audit->log($userId, 'photo.uploaded', 'product_photo', $newId, 'Foto diunggah untuk produk ' . $product->name);

                return $newId;
            });
        } catch (\Throwable $e) {
            // DB gagal sesudah file tertulis — jangan tinggalkan yatim.
            @unlink($target);

            throw $e;
        }

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * Hapus baris DB + file fisik. File dihapus hanya jika path-nya
     * benar-benar berada di dalam folder upload produk (anti traversal).
     */
    public function delete(int $photoId, ?int $userId): void
    {
        $photo = $this->products->findPhoto($photoId);
        if ($photo === null) {
            throw new HttpException(404, 'Foto tidak ditemukan.');
        }

        $absolute = $this->absolutePath((string) $photo['file_path']);

        $this->db->transaction(function () use ($photoId, $photo, $userId): void {
            $this->products->deletePhoto($photoId);
            $this->audit->log(
                $userId,
                'photo.deleted',
                'product_photo',
                $photoId,
                'Foto dihapus: ' . (string) $photo['original_name'],
            );
        });

        if ($absolute !== null && is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * Baca konten file untuk streaming. Menolak path di luar folder upload.
     *
     * @return array{path: string, mime: string, size: int}
     */
    public function readForStreaming(array $photo): array
    {
        $absolute = $this->absolutePath((string) $photo['file_path']);
        if ($absolute === null || !is_file($absolute) || !is_readable($absolute)) {
            throw new HttpException(404, 'Berkas foto tidak ditemukan di server.');
        }

        return [
            'path' => $absolute,
            'mime' => (string) $photo['mime'],
            'size' => (int) filesize($absolute),
        ];
    }

    /**
     * Row foto + produk pemiliknya, untuk pengecekan policy sebelum serve.
     *
     * @return array<string, mixed>|null
     */
    public function photoRow(int $photoId): ?array
    {
        return $this->products->findPhoto($photoId);
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed>|null $file
     * @return array<string, list<string>>
     */
    private function inspectUpload(?array $file): array
    {
        if ($file === null || !isset($file['error'])) {
            return ['photo' => 'Tidak ada file yang diunggah.'];
        }

        $error = (int) $file['error'];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return ['photo' => ['Ukuran file melebihi batas ' . $this->maxLabel() . '.']];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['photo' => ['Unggahan gagal (kode ' . $error . '). Coba lagi.']];
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return ['photo' => ['File tidak valid.']];
        }

        if ((int) $file['size'] <= 0 || (int) $file['size'] > self::MAX_BYTES) {
            return ['photo' => ['Ukuran file harus antara 1 byte dan ' . $this->maxLabel() . '.']];
        }

        return [];
    }

    private function detectMime(string $tmpPath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            throw new HttpException(400, 'Jenis file tidak diizinkan. Gunakan JPG, PNG, atau WebP.');
        }

        return $mime;
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new HttpException(500, 'Folder penyimpanan foto tidak dapat dibuat.');
        }
    }

    /** Path absolut hanya jika relative path bersih di dalam folder upload. */
    private function absolutePath(string $relativePath): ?string
    {
        if (preg_match('#^storage/uploads/products/[a-f0-9]{32}\.(jpg|png|webp)$#', $relativePath) !== 1) {
            return null;
        }

        return BASE_PATH . '/' . $relativePath;
    }

    private function maxLabel(): string
    {
        return intdiv(self::MAX_BYTES, 1024 * 1024) . ' MB';
    }
}
