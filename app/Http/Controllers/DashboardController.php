<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;
use App\Services\Auth;
use App\Services\DashboardService;

/**
 * Dashboard pasca-login (§8.A) — cockpit operasional. Mengutamakan pekerjaan
 * yang perlu ditindaklanjuti. Setiap panel digate per peran mengikuti set akses
 * modul terkait (angka KPI untuk semua pengguna terautentikasi).
 */
final class DashboardController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly DashboardService $dashboard,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();

        return Response::html($this->view->render('dashboard.index', [
            'title' => 'Dashboard — JahitFlow',
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Ringkasan pekerjaan yang perlu ditindaklanjuti hari ini.',
            'user' => $user,
            'dashboard' => $this->dashboard->compose(),
            'canSeeFinance' => $this->allows($user, [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_KEUANGAN]),
            'canSeeOrders' => $this->allows($user, [User::ROLE_ADMIN, User::ROLE_MANAGER]),
            'canSeeProduction' => $this->allows($user, [User::ROLE_ADMIN, User::ROLE_MANAGER, User::ROLE_OPERATOR]),
            'canSeeDeliveries' => $this->allows($user, [User::ROLE_ADMIN, User::ROLE_MANAGER]),
        ]));
    }

    /** @param list<string> $roles */
    private function allows(?User $user, array $roles): bool
    {
        return $user?->hasRole($roles) ?? false;
    }
}
