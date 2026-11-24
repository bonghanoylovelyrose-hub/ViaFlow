<?php

namespace App\Http\Controllers;

use App\Services\Dss\IncidentDssService;
use App\Services\Dss\LoanDecisionReportService;
use App\Services\Dss\LoanDssService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DssController extends Controller
{
    public function incidents(Request $request, IncidentDssService $service): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.dss.incidents', [
            'dss' => $service->build(),
        ]);
    }

    public function loans(
        Request $request,
        LoanDssService $service,
        LoanDecisionReportService $report,
    ): Response {
        abort_unless($request->user()?->isAdmin(), 403);

        $normalizedFilters = [
            'year' => 'all',
            'status' => 'all',
        ];

        $dss = $service->build($request->only(['year', 'month']));
        $decisionReport = $report->build($normalizedFilters);
        $decisionReport['applications']->appends([
            'year' => $dss['filters']['year'],
            'month' => $dss['filters']['month'],
        ]);

        return response()->view('admin.dss.loans', compact('dss', 'decisionReport'))
            ->header('Cache-Control', 'private, no-store');
    }
}
