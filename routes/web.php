<?php

declare(strict_types=1);

/**
 * Route publik aplikasi.
 *
 * Aturan penamaan: <modul>.<aksi>. Middleware per-route ditulis sebagai
 * FQCN kelas middleware; yang berparameter pakai format 'Class:param'
 * (mis. App\Http\Middleware\RoleMiddleware::class . ':admin').
 */

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPhotoController;
use App\Http\Controllers\ProductionOrderController;
use App\Http\Controllers\QcController;
use App\Http\Controllers\RawMaterialController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\GuestMiddleware;
use App\Core\Router;

/** @var Router $router */

// Halaman masuk: hanya untuk tamu.
$router->get('/login', AuthController::class . '@showLogin', name: 'login', middleware: [GuestMiddleware::class]);
$router->post('/login', AuthController::class . '@login', name: 'login.attempt', middleware: [GuestMiddleware::class]);
$router->post('/logout', AuthController::class . '@logout', name: 'logout', middleware: [AuthMiddleware::class]);

// Area terproteksi: semua butuh login (AuthMiddleware).
$router->group([AuthMiddleware::class], static function (Router $router): void {
    $router->get('/dashboard', DashboardController::class . '@index', name: 'dashboard');

    // Halaman verifikasi fondasi Fase 1 — kini di belakang login.
    $router->get('/_foundation', HomeController::class . '@index', name: 'foundation');
    $router->post('/_foundation/flash-demo', HomeController::class . '@flashDemo', name: 'foundation.flashDemo');

    // --- Fase 4 Batch A: Pelanggan. Otorisasi per-aksi lewat CustomerPolicy
    //     (panggil di controller), bukan middleware, karena bergantung entitas.
    $router->get('/pelanggan', CustomerController::class . '@index', name: 'customers.index');
    $router->get('/pelanggan/baru', CustomerController::class . '@create', name: 'customers.create');
    $router->post('/pelanggan', CustomerController::class . '@store', name: 'customers.store');
    $router->get('/pelanggan/{id}', CustomerController::class . '@show', name: 'customers.show');
    $router->get('/pelanggan/{id}/ubah', CustomerController::class . '@edit', name: 'customers.edit');
    $router->post('/pelanggan/{id}', CustomerController::class . '@update', name: 'customers.update');
    $router->post('/pelanggan/{id}/status/{status}', CustomerController::class . '@setStatus', name: 'customers.setStatus');

    // --- Kategori produk.
    $router->get('/kategori', ProductCategoryController::class . '@index', name: 'categories.index');
    $router->post('/kategori', ProductCategoryController::class . '@store', name: 'categories.store');
    $router->post('/kategori/{id}', ProductCategoryController::class . '@update', name: 'categories.update');
    $router->post('/kategori/{id}/hapus', ProductCategoryController::class . '@destroy', name: 'categories.destroy');

    // --- Katalog produk + varian.
    $router->get('/produk', ProductController::class . '@index', name: 'products.index');
    $router->get('/produk/baru', ProductController::class . '@create', name: 'products.create');
    $router->post('/produk', ProductController::class . '@store', name: 'products.store');
    $router->get('/produk/{id}', ProductController::class . '@show', name: 'products.show');
    $router->get('/produk/{id}/ubah', ProductController::class . '@edit', name: 'products.edit');
    $router->post('/produk/{id}', ProductController::class . '@update', name: 'products.update');
    $router->post('/produk/{id}/status/{status}', ProductController::class . '@setStatus', name: 'products.setStatus');
    $router->post('/produk/{id}/varian', ProductController::class . '@addVariant', name: 'products.addVariant');
    $router->post('/produk/varian/{variantId}/harga', ProductController::class . '@updateVariantPrice', name: 'products.variantPrice');
    $router->post('/produk/varian/{variantId}/status/{status}', ProductController::class . '@setVariantStatus', name: 'products.variantStatus');

    // --- Foto produk: unggah/hapus (manage) dan streaming (login).
    $router->post('/produk/{id}/foto', ProductPhotoController::class . '@store', name: 'products.photo.store');
    $router->post('/produk/foto/{photoId}/hapus', ProductPhotoController::class . '@destroy', name: 'products.photo.destroy');
    $router->get('/produk/foto/{photoId}', ProductPhotoController::class . '@show', name: 'products.photo.show');

    // --- Fase 4 Batch B: Sales Order. Otorisasi & transisi status lewat
    //     SalesOrderPolicy + SalesOrderService. Segmen literal (baru) sebelum
    //     wildcard {id} agar tidak tertangkap pola parameter.
    $router->get('/pesanan', SalesOrderController::class . '@index', name: 'sales_orders.index');
    $router->get('/pesanan/baru', SalesOrderController::class . '@create', name: 'sales_orders.create');
    $router->post('/pesanan', SalesOrderController::class . '@store', name: 'sales_orders.store');
    $router->get('/pesanan/{id}', SalesOrderController::class . '@show', name: 'sales_orders.show');
    $router->get('/pesanan/{id}/unduh/csv', SalesOrderController::class . '@unduhCsv', name: 'sales_orders.unduh.csv');
    $router->get('/pesanan/{id}/unduh/pdf', SalesOrderController::class . '@unduhPdf', name: 'sales_orders.unduh.pdf');
    $router->get('/pesanan/{id}/ubah', SalesOrderController::class . '@edit', name: 'sales_orders.edit');
    $router->post('/pesanan/{id}', SalesOrderController::class . '@update', name: 'sales_orders.update');
    $router->post('/pesanan/{id}/tawarkan', SalesOrderController::class . '@offer', name: 'sales_orders.offer');
    $router->post('/pesanan/{id}/konfirmasi', SalesOrderController::class . '@confirm', name: 'sales_orders.confirm');
    $router->post('/pesanan/{id}/batalkan', SalesOrderController::class . '@cancel', name: 'sales_orders.cancel');
    $router->post('/pesanan/{id}/hapus', SalesOrderController::class . '@destroy', name: 'sales_orders.destroy');

    // --- Fase 4 Batch C: Production Order. Pembuatan dari pesanan + transisi
    //     tahap dijaga ProductionOrderPolicy + ProductionService. Segmen literal
    //     (baru, tahap) didaftarkan sebelum wildcard {id}.
    $router->get('/produksi', ProductionOrderController::class . '@index', name: 'produksi.index');
    $router->get('/produksi/baru', ProductionOrderController::class . '@create', name: 'produksi.create');
    $router->post('/produksi', ProductionOrderController::class . '@store', name: 'produksi.store');
    $router->post('/produksi/tahap/{stageId}/{action}', ProductionOrderController::class . '@updateStage', name: 'produksi.stage');
    $router->get('/produksi/{id}', ProductionOrderController::class . '@show', name: 'produksi.show');
    $router->get('/produksi/{id}/unduh/csv', ProductionOrderController::class . '@unduhCsv', name: 'produksi.unduh.csv');
    $router->get('/produksi/{id}/unduh/pdf', ProductionOrderController::class . '@unduhPdf', name: 'produksi.unduh.pdf');
    $router->post('/produksi/{id}/batalkan', ProductionOrderController::class . '@cancel', name: 'produksi.cancel');

    // --- Fase 4 Batch D: Bahan Baku & Stok. Master CRUD + pencatatan ledger.
    //     Arah pergerakan dibaca dari input (divalidasi service). Segmen literal
    //     (baru) sebelum wildcard {id}.
    $router->get('/bahan-baku', RawMaterialController::class . '@index', name: 'materials.index');
    $router->get('/bahan-baku/baru', RawMaterialController::class . '@create', name: 'materials.create');
    $router->post('/bahan-baku', RawMaterialController::class . '@store', name: 'materials.store');
    $router->get('/bahan-baku/{id}', RawMaterialController::class . '@show', name: 'materials.show');
    $router->get('/bahan-baku/{id}/ubah', RawMaterialController::class . '@edit', name: 'materials.edit');
    $router->post('/bahan-baku/{id}', RawMaterialController::class . '@update', name: 'materials.update');
    $router->post('/bahan-baku/{id}/status/{status}', RawMaterialController::class . '@setStatus', name: 'materials.setStatus');
    $router->post('/bahan-baku/{id}/stok', RawMaterialController::class . '@record', name: 'materials.stock');

    // --- Fase 4 Batch E1: Quality Control. Pemeriksaan (log) menempel pada
    //     perintah produksi berjalan/selesai. Segmen literal 'baru' sebelum {id}.
    $router->get('/qc', QcController::class . '@index', name: 'qc.index');
    $router->get('/qc/baru', QcController::class . '@create', name: 'qc.create');
    $router->post('/qc', QcController::class . '@store', name: 'qc.store');
    $router->get('/qc/{id}', QcController::class . '@show', name: 'qc.show');
    
        // --- Fase 4 Batch E2: Pembayaran. Transaksi uang masuk (log) menempel pada
        //     Sales Order; penomoran 'BYR' via sequence. Segmen literal 'baru' sebelum {id}.
    $router->get('/pembayaran', PaymentController::class . '@index', name: 'payments.index');
    $router->get('/pembayaran/baru', PaymentController::class . '@create', name: 'payments.create');
    $router->post('/pembayaran', PaymentController::class . '@store', name: 'payments.store');
    $router->get('/pembayaran/{id}', PaymentController::class . '@show', name: 'payments.show');
    
        // --- Fase 4 Batch E3: Pengiriman. Batch per Sales Order dengan status yang
        //     bertransisi (disiapkan→dikirim→diterima/batal). Segmen literal 'baru' &
        //     '{id}/status' diatur sebelum wildcard {id} agar tidak tertelan.
    $router->get('/pengiriman', DeliveryController::class . '@index', name: 'deliveries.index');
    $router->get('/pengiriman/baru', DeliveryController::class . '@create', name: 'deliveries.create');
    $router->post('/pengiriman', DeliveryController::class . '@store', name: 'deliveries.store');
    $router->get('/pengiriman/{id}', DeliveryController::class . '@show', name: 'deliveries.show');
    $router->post('/pengiriman/{id}/status', DeliveryController::class . '@updateStatus', name: 'deliveries.status');

    // --- Fase 4 Batch F: Laporan (§8.J). Read-only. Hub + satu renderer generik
    //     per laporan ({key}); ekspor CSV lewat query ?export=csv pada route yang sama.
    $router->get('/laporan', ReportsController::class . '@index', name: 'reports.index');
    $router->get('/laporan/{key}', ReportsController::class . '@show', name: 'reports.show');
});

// Titik masuk: redirect menyesuaikan status login (lihat HomeController::root).
$router->get('/', HomeController::class . '@root', name: 'home');
