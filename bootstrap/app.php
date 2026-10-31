<?php

declare(strict_types=1);

/**
 * Bootstrap aplikasi — satu-satunya tempat wiring dependency manual.
 *
 * Urutan: env → config → container (semua binding) → routes → Application.
 * File ini di-require oleh public/index.php dan tests/bootstrap.php.
 */

use App\Core\Application;
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exceptions\ExceptionHandler;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/vendor/autoload.php';

// 1) Environment harus lebih dulu: config file membacanya.
Env::load(BASE_PATH . '/.env');

$config = new Config(BASE_PATH . '/config');
$config->load();

date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Jakarta'));

$container = new Container();
$container->instance(Config::class, $config);

// 2) Binding inti — eksplisit, terbaca, mudah diganti saat testing.
$container->singleton(Session::class, fn(): Session => new Session($config->get('session')));

$container->singleton(Logger::class, fn(): Logger => new Logger(BASE_PATH . '/storage/logs'));

$container->singleton(Database::class, fn(): Database => new Database($config->get('database')));

$container->singleton(View::class, fn(): View => new View(BASE_PATH . '/resources/views'));

$container->singleton(Router::class, fn(): Router => new Router());

$container->singleton(ExceptionHandler::class, fn(Container $c): ExceptionHandler => new ExceptionHandler(
    $c->make(Logger::class),
    (bool) $config->get('app.debug', false),
    $c->make(View::class),
    $c->make(Session::class),
));

$container->bind(Request::class, fn(): Request => Request::capture());

$container->bind(App\Http\Middleware\CsrfMiddleware::class, fn(Container $c): App\Http\Middleware\CsrfMiddleware =>
    new App\Http\Middleware\CsrfMiddleware($c->make(Session::class)));

$container->bind(App\Http\Controllers\HomeController::class, fn(Container $c): App\Http\Controllers\HomeController =>
    new App\Http\Controllers\HomeController(
        $c->make(View::class),
        $c->make(Session::class),
        $c->make(Database::class),
        $c->make(App\Services\Auth::class),
    ));

// 2b) Layer Fase 3 — auth: repositories → guard → middleware → controllers.
//     Dependensi tetap manual & terbaca; tidak ada auto-wire.
$container->singleton(App\Repositories\UserRepository::class, fn(Container $c): App\Repositories\UserRepository =>
    new App\Repositories\UserRepository($c->make(Database::class)));

$container->singleton(App\Repositories\LoginAttemptRepository::class, fn(Container $c): App\Repositories\LoginAttemptRepository =>
    new App\Repositories\LoginAttemptRepository($c->make(Database::class)));

$container->singleton(App\Services\Auth::class, fn(Container $c): App\Services\Auth => new App\Services\Auth(
    $c->make(Session::class),
    $c->make(App\Repositories\UserRepository::class),
    $c->make(App\Repositories\LoginAttemptRepository::class),
    $c->make(Config::class),
));

$container->bind(App\Http\Middleware\AuthMiddleware::class, fn(Container $c): App\Http\Middleware\AuthMiddleware =>
    new App\Http\Middleware\AuthMiddleware($c->make(App\Services\Auth::class), $c->make(Session::class)));

$container->bind(App\Http\Middleware\GuestMiddleware::class, fn(Container $c): App\Http\Middleware\GuestMiddleware =>
    new App\Http\Middleware\GuestMiddleware($c->make(App\Services\Auth::class)));

// RoleMiddleware stateful (menerima param) → bind (instance baru per request).
$container->bind(App\Http\Middleware\RoleMiddleware::class, fn(Container $c): App\Http\Middleware\RoleMiddleware =>
    new App\Http\Middleware\RoleMiddleware($c->make(App\Services\Auth::class)));

$container->bind(App\Http\Controllers\AuthController::class, fn(Container $c): App\Http\Controllers\AuthController =>
    new App\Http\Controllers\AuthController(
        $c->make(View::class),
        $c->make(Session::class),
        $c->make(App\Services\Auth::class),
        new App\Validators\LoginValidator(),
    ));

$container->bind(App\Http\Controllers\DashboardController::class, fn(Container $c): App\Http\Controllers\DashboardController =>
    new App\Http\Controllers\DashboardController(
        $c->make(View::class),
        $c->make(App\Services\Auth::class),
        $c->make(App\Services\DashboardService::class),
    ));

// 2c) Layer Fase 4 Batch A — Pelanggan & Katalog Produk. Dependensi seluruhnya
//     bertipe class (Database/Session/View sudah terbind), jadi dipakai
//     Container::auto() (refleksi) alih-alih menulis wiring manual panjang.
//     Lihat App\Core\Factory: scalar tanpa default akan error jelas.

// Repositories & services.
$container->auto(App\Repositories\AuditRepository::class);
$container->auto(App\Repositories\SequenceRepository::class);
$container->auto(App\Repositories\CustomerRepository::class);
$container->auto(App\Repositories\ProductCategoryRepository::class);
$container->auto(App\Repositories\ProductRepository::class);

$container->auto(App\Validators\CustomerValidator::class);
$container->auto(App\Validators\ProductValidator::class);
$container->auto(App\Validators\VariantValidator::class);
$container->auto(App\Validators\CategoryValidator::class);

$container->auto(App\Services\CustomerService::class);
$container->auto(App\Services\ProductCategoryService::class);
$container->auto(App\Services\ProductService::class);
$container->auto(App\Services\ProductPhotoService::class);

// Policies (stateless) & controllers (bind: instance baru per request).
$container->auto(App\Policies\CustomerPolicy::class);
$container->auto(App\Policies\ProductPolicy::class);

$container->auto(App\Http\Controllers\CustomerController::class, false);
$container->auto(App\Http\Controllers\ProductController::class, false);
$container->auto(App\Http\Controllers\ProductCategoryController::class, false);
$container->auto(App\Http\Controllers\ProductPhotoController::class, false);

// 2d) Layer Fase 4 Batch B — Sales Order.
$container->auto(App\Repositories\SalesOrderRepository::class);
$container->auto(App\Validators\SalesOrderValidator::class);
$container->auto(App\Services\SalesOrderService::class);
$container->auto(App\Policies\SalesOrderPolicy::class);
$container->auto(App\Http\Controllers\SalesOrderController::class, false);

// 2e) Layer Fase 4 Batch C — Production Order. Tahap bersifat data-driven
//     (ProductionStageRepository); transisi progres dijaga ProductionStageMachine
//     murni, orkestrasi (gerbang DP, penomoran 'PP', snapshot tahap, audit) di
//     ProductionService. Controller di-bind (instance baru per request).
$container->auto(App\Repositories\ProductionStageRepository::class);
$container->auto(App\Repositories\ProductionOrderRepository::class);
$container->auto(App\Validators\ProductionOrderValidator::class);
$container->auto(App\Services\ProductionService::class);
$container->auto(App\Policies\ProductionOrderPolicy::class);
$container->auto(App\Http\Controllers\ProductionOrderController::class, false);

// 2f) Layer Fase 4 Batch D — Bahan Baku & Stok. Saldo diubah HANYA lewat
//     ledger StockService (SELECT ... FOR UPDATE + StockLedger murni), bukan
//     lewat CRUD master. Uniqueness kode bahan dicek DB (bukan MAX(id)+1).
$container->auto(App\Repositories\RawMaterialRepository::class);
$container->auto(App\Repositories\StockMovementRepository::class);
$container->auto(App\Validators\RawMaterialValidator::class);
$container->auto(App\Validators\StockMovementValidator::class);
$container->auto(App\Services\RawMaterialService::class);
$container->auto(App\Services\StockService::class);
$container->auto(App\Policies\RawMaterialPolicy::class);
$container->auto(App\Http\Controllers\RawMaterialController::class, false);

// 2g) Layer Fase 4 Batch E1 — Quality Control. Pemeriksaan menempel pada
//     perintah produksi (berjalan/selesai); bersifat log (tanpa edit). Aturan
//     jumlah dijaga QcInspectionValidator + CHECK di DB; audit tiap pemeriksaan.
$container->auto(App\Repositories\QcInspectionRepository::class);
$container->auto(App\Validators\QcInspectionValidator::class);
$container->auto(App\Services\QcService::class);
$container->auto(App\Policies\QcPolicy::class);
$container->auto(App\Http\Controllers\QcController::class, false);

// 2h) Layer Fase 4 Batch E2 — Pembayaran. Transaksi uang masuk menempel pada
//     Sales Order; bersifat log (tanpa edit). Penomoran via SequenceRepository
//     ('BYR', bukan MAX(id)+1). Batas "<= sisa tagihan" dijaga PaymentService
//     (SUM payments sebagai sumber kebenaran); dp_amount tetap hanya gerbang
//     produksi. Audit tiap transaksi.
$container->auto(App\Repositories\PaymentRepository::class);
$container->auto(App\Validators\PaymentValidator::class);
$container->auto(App\Services\PaymentService::class);
$container->auto(App\Policies\PaymentPolicy::class);
$container->auto(App\Http\Controllers\PaymentController::class, false);

// 2i) Layer Fase 4 Batch E3 — Pengiriman. Satu batch = record dengan status yang
//     bertransisi eksplisit (DeliveryFlow). Penomoran via SequenceRepository
//     ('KRM', bukan MAX(id)+1). Pagar §9 (jumlah ≤ min(pesanan, lolos QC) − sudah
//     dikirim non-batal) dihitung otoritatif di DeliveryService. Audit tiap aksi.
$container->auto(App\Repositories\DeliveryRepository::class);
$container->auto(App\Validators\DeliveryValidator::class);
$container->auto(App\Services\DeliveryService::class);
$container->auto(App\Policies\DeliveryPolicy::class);
$container->auto(App\Http\Controllers\DeliveryController::class, false);

// 2j) Layer Fase 4 Batch F — Laporan (§8.J). Read-only: ReportRepository hanya
//     menjalankan SELECT agregasi lintas modul; ReportService menyusun deskriptor
//     (KPI + tabel bertipe) yang dipakai bersama oleh layar dan ekspor CSV.
$container->auto(App\Repositories\ReportRepository::class);
$container->auto(App\Services\ReportService::class);
$container->auto(App\Policies\ReportPolicy::class);
$container->auto(App\Http\Controllers\ReportsController::class, false);

// 2k) Fase 4 Batch G — Dashboard nyata (§8.A). Read-only: DashboardRepository
//     menjalankan SELECT agregasi lintas modul; DashboardService mendekorasi baris
//     (label/badge/sisa-hari) menjadi model view. Controller sudah terbind manual
//     (Fase 3) dan kini ikut menerima DashboardService.
$container->auto(App\Repositories\DashboardRepository::class);
$container->auto(App\Services\DashboardService::class);

// 3) Routes.
$router = $container->make(Router::class);
require BASE_PATH . '/routes/web.php';

// 4) Aplikasi. Middleware global: CSRF ditegakkan di SEMUA request
//    yang mengubah data, termasuk request HTMX (aturan §6 & R3).
return new Application(
    container: $container,
    router: $router,
    session: $container->make(Session::class),
    view: $container->make(View::class),
    errorHandler: $container->make(ExceptionHandler::class),
    globalMiddleware: [
        App\Http\Middleware\CsrfMiddleware::class,
    ],
);
