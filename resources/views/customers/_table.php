<?php

use App\Models\Customer;

/**
 * Partial daftar pelanggan — jawaban HTMX sekaligus render awal.
 * Tidak ada logika query di sini; hanya menampilkan $customers.
 *
 * @var App\Core\View $view
 * @var list<Customer> $customers
 * @var App\Support\Paginator $paginator
 * @var string $search
 * @var string $status
 * @var bool $canSetStatus
 */
?>
<div class="jf-table-wrap">
    <?php if ($customers === []): ?>
        <div class="jf-empty">
            <p class="jf-empty-title">Belum ada pelanggan</p>
            <p class="jf-empty-text">
                <?= $search !== '' || $status !== 'aktif'
                    ? 'Tidak ada pelanggan yang cocok dengan filter ini.'
                    : 'Tambahkan pelanggan pertama untuk mulai mencatat pesanan.' ?>
            </p>
        </div>
    <?php else: ?>
        <table class="jf-table">
            <thead>
                <tr>
                    <th scope="col">Nama</th>
                    <th scope="col">Tipe</th>
                    <th scope="col">Kontak</th>
                    <th scope="col">Kota</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="jf-text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <a class="jf-link" href="<?= e(route('customers.show', ['id' => $customer->id])) ?>">
                                <?= e($customer->name) ?>
                            </a>
                        </td>
                        <td><?= e($customer->typeLabel()) ?></td>
                        <td>
                            <div><?= e($customer->phone) ?></div>
                            <?php if ($customer->contactPerson): ?>
                                <div class="jf-text-muted jf-sm"><?= e($customer->contactPerson) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($customer->city ?? '—') ?></td>
                        <td>
                            <?php if ($customer->isActive): ?>
                                <span class="jf-badge jf-badge--success">Aktif</span>
                            <?php else: ?>
                                <span class="jf-badge jf-badge--warning">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="jf-text-right jf-actions">
                            <a class="jf-btn jf-btn--sm jf-btn--ghost" href="<?= e(route('customers.edit', ['id' => $customer->id])) ?>">Ubah</a>
                            <?php if ($canSetStatus): ?>
                                <form method="post" action="<?= e(route('customers.setStatus', ['id' => $customer->id, 'status' => $customer->isActive ? 'nonaktif' : 'aktif'])) ?>"
                                      class="jf-inline"
                                      <?= $customer->isActive ? 'onsubmit="return confirm(\'Nonaktifkan pelanggan ini? Riwayat pesanan tetap tersimpan.\')"' : '' ?>>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="jf-btn jf-btn--sm <?= $customer->isActive ? 'jf-btn--danger' : 'jf-btn--primary' ?>">
                                        <?= $customer->isActive ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?= $view->include('partials.pagination', [
            'paginator' => $paginator,
            'baseUrl' => route('customers.index'),
            'query' => ['q' => $search, 'status' => $status],
            'hxTarget' => '#customers-table',
        ]) ?>
    <?php endif; ?>
</div>
