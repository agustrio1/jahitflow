<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\RawMaterial;
use App\Repositories\AuditRepository;
use App\Repositories\RawMaterialRepository;
use App\Validators\RawMaterialValidator;

/**
 * Logika bisnis master bahan baku (§8.F). CRUD + uniqueness kode (dicek DB,
 * bukan MAX(id)+1) + audit. Saldo TIDAK diubah di sini — itu wilayah
 * StockService lewat ledger.
 */
final class RawMaterialService
{
    public function __construct(
        private readonly Database $db,
        private readonly RawMaterialRepository $materials,
        private readonly AuditRepository $audit,
        private readonly RawMaterialValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        $errors = $this->validator->validate($data);
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if (!isset($errors['code']) && $code !== '' && $this->materials->codeExists($code)) {
            $errors['code'][] = 'Kode bahan sudah dipakai.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $id = $this->db->transaction(function () use ($data, $userId): int {
            $newId = $this->materials->create($data);
            $this->audit->log($userId, 'material.created', 'raw_material', $newId, 'Bahan dibuat: ' . $data['code'] . ' — ' . trim((string) $data['name']));

            return $newId;
        });

        return ['ok' => true, 'id' => $id, 'errors' => []];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, errors: array<string, list<string>>}
     */
    public function update(int $id, array $data, ?int $userId): array
    {
        $material = $this->findOrFail($id);

        $errors = $this->validator->validate($data);
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if (!isset($errors['code']) && $code !== '' && $this->materials->codeExists($code, $id)) {
            $errors['code'][] = 'Kode bahan sudah dipakai bahan lain.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->db->transaction(function () use ($id, $data, $userId, $material): void {
            $this->materials->update($id, $data);
            $this->audit->log(
                $userId,
                'material.updated',
                'raw_material',
                $id,
                'Bahan diperbarui: ' . $material->code . ' → ' . strtoupper(trim((string) $data['code'])),
            );
        });

        return ['ok' => true, 'errors' => []];
    }

    public function setStatus(int $id, bool $active, ?int $userId): void
    {
        $material = $this->findOrFail($id);

        $this->db->transaction(function () use ($id, $active, $userId, $material): void {
            $this->materials->setActive($id, $active);
            $this->audit->log(
                $userId,
                $active ? 'material.activated' : 'material.deactivated',
                'raw_material',
                $id,
                ($active ? 'Bahan diaktifkan: ' : 'Bahan dinonaktifkan: ') . $material->name,
            );
        });
    }

    public function findOrFail(int $id): RawMaterial
    {
        $material = $this->materials->find($id);
        if ($material === null) {
            throw new HttpException(404, 'Bahan baku tidak ditemukan.');
        }

        return $material;
    }
}
