<?php

declare(strict_types=1);

/**
 * Struktur navigasi sidebar (§2 DOC: 9 modul bisnis + dashboard).
 *
 * - 'roles'  : null = semua role; atau list role yang boleh melihat.
 * - 'built'  : true = route sudah tersedia (tautan aktif);
 *              false = modul belum dibangun → ditampilkan nonaktif
 *              (JUJUR, tidak mengarah ke 404 palsu).
 * - 'path'   : dipakai untuk penanda item aktif (vs currentPath).
 *
 * Saat modul Fase 4 dibangun, ubah built=>true dan tambahkan 'route'
 * untuk pembangkitan URL via nama route.
 */

return [
    'sections' => [
        [
            'label' => 'Utama',
            'items' => [
                ['label' => 'Dashboard', 'path' => '/dashboard', 'icon' => 'grid', 'roles' => null, 'built' => true],
            ],
        ],
        [
            'label' => 'Operasional',
            'items' => [
                ['label' => 'Pelanggan', 'path' => '/pelanggan', 'icon' => 'users', 'roles' => ['admin', 'manager'], 'built' => true],
                ['label' => 'Katalog Produk', 'path' => '/produk', 'icon' => 'box', 'roles' => null, 'built' => true],
                ['label' => 'Kategori Produk', 'path' => '/kategori', 'icon' => 'tag', 'roles' => ['admin', 'manager'], 'built' => true],
                ['label' => 'Sales Order', 'path' => '/pesanan', 'icon' => 'cart', 'roles' => ['admin', 'manager'], 'built' => true],
                ['label' => 'Production Order', 'path' => '/produksi', 'icon' => 'factory', 'roles' => ['admin', 'manager', 'operator'], 'built' => true],
                ['label' => 'Bahan Baku & Stok', 'path' => '/bahan-baku', 'icon' => 'layers', 'roles' => ['admin', 'manager', 'operator'], 'built' => true],
                ['label' => 'Quality Control', 'path' => '/qc', 'icon' => 'check', 'roles' => ['admin', 'manager', 'operator'], 'built' => true],
            ],
        ],
        [
            'label' => 'Keuangan & Pengiriman',
            'items' => [
                ['label' => 'Pembayaran', 'path' => '/pembayaran', 'icon' => 'wallet', 'roles' => ['admin', 'keuangan'], 'built' => true],
                                ['label' => 'Pengiriman', 'path' => '/pengiriman', 'icon' => 'truck', 'roles' => ['admin', 'manager'], 'built' => true],
            ],
        ],
        [
            'label' => 'Analitik & Sistem',
            'items' => [
                ['label' => 'Laporan', 'path' => '/laporan', 'icon' => 'chart', 'roles' => ['admin', 'manager', 'keuangan'], 'built' => true],
                ['label' => 'Verifikasi Fondasi', 'path' => '/_foundation', 'icon' => 'beaker', 'roles' => null, 'built' => true],
            ],
        ],
    ],
];
