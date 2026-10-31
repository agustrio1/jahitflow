<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Policies\ReportPolicy;
use App\Services\Auth;
use App\Services\ReportService;
use App\Support\ReportPeriod;

/**
 * Laporan (§8.J). Controller tipis & read-only: policy → service → view.
 * Satu halaman hub berisi kartu laporan, dan satu renderer generik per laporan
 * yang ikut melayani ekspor CSV (query ?export=csv) memakai periode yang sama.
 */
final class ReportsController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly ReportService $reports,
        private readonly ReportPolicy $policy,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->viewAny($user);

        return Response::html($this->view->render('reports/index', [
            'title' => 'Laporan — JahitFlow',
            'pageTitle' => 'Laporan',
            'pageSubtitle' => 'Analitik lintas modul. Semua angka bersumber dari query yang konsisten dengan aturan bisnis.',
            'reports' => $this->reports->available(),
        ]));
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->user();
        $this->policy->view($user);

        $key = (string) $request->routeParam('key');
        $period = ReportPeriod::resolve($request->query('dari'), $request->query('sampai'));

        // Ekspor CSV: gate terpisah, descriptor yang sama dengan layar.
        if ($request->query('export') === 'csv') {
            $this->policy->export($user);
            $filename = sprintf('laporan-%s-%s_%s.csv', $key, $period->fromDate(), $period->toDate());

            return Response::csv($this->reports->csv($key, $period), $filename);
        }

        $report = $this->reports->build($key, $period);

        return Response::html($this->view->render('reports/show', [
            'title' => $report['title'] . ' — JahitFlow',
            'pageTitle' => $report['title'],
            'pageSubtitle' => $report['subtitle'],
            'report' => $report,
            'from' => $period->fromDate(),
            'to' => $period->toDate(),
        ]));
    }
}
