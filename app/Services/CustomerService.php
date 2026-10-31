<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Models\Customer;
use App\Repositories\AuditRepository;
use App\Repositories\CustomerRepository;
use App\Validators\CustomerValidator;

/**
 * Logika bisnis pelanggan (§7.1 DOC: business logic di service).
 * Setiap write = transaksi + audit trail. Validator input terpisah.
 */
final class CustomerService
{
    public function __construct(
        private readonly Database $db,
        private readonly CustomerRepository $customers,
        private readonly AuditRepository $audit,
        private readonly CustomerValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, id: int|null, errors: array<string, list<string>>}
     */
    public function create(array $data, ?int $userId): array
    {
        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            return ['ok' => false, 'id' => null, 'errors' => $errors];
        }

        $id = $this->db->transaction(function () use ($data, $userId): int {
            $newId = $this->customers->create($data);
            $this->audit->log($userId, 'customer.created', 'customer', $newId, 'Pelanggan dibuat: ' . trim((string) $data['name']));

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
        $customer = $this->findOrFail($id);

        $errors = $this->validator->validate($data);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->db->transaction(function () use ($id, $data, $userId, $customer): void {
            $this->customers->update($id, $data);
            $this->audit->log(
                $userId,
                'customer.updated',
                'customer',
                $id,
                'Pelanggan diperbarui: ' . $customer->name . ' → ' . trim((string) $data['name']),
            );
        });

        return ['ok' => true, 'errors' => []];
    }

    /** Nonaktifkan/aktifkan kembali — soft delete, riwayat pesanan tetap utuh. */
    public function setStatus(int $id, bool $active, ?int $userId): void
    {
        $customer = $this->findOrFail($id);

        $this->db->transaction(function () use ($id, $active, $userId, $customer): void {
            $this->customers->setActive($id, $active);
            $this->audit->log(
                $userId,
                $active ? 'customer.activated' : 'customer.deactivated',
                'customer',
                $id,
                ($active ? 'Pelanggan diaktifkan: ' : 'Pelanggan dinonaktifkan: ') . $customer->name,
            );
        });
    }

    public function findOrFail(int $id): Customer
    {
        $customer = $this->customers->find($id);
        if ($customer === null) {
            throw new HttpException(404, 'Pelanggan tidak ditemukan.');
        }

        return $customer;
    }
}
