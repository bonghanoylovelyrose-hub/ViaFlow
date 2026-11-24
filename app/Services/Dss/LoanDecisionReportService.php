<?php

namespace App\Services\Dss;

use App\Models\Loan;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LoanDecisionReportService
{
    public function build(array $filters): array
    {
        $decisions = Loan::query()->with(['consumer', 'incomeVerifier', 'latestDssEvaluation'])
            ->whereHas('latestDssEvaluation')
            ->get()
            ->filter(fn (Loan $loan): bool => in_array($loan->latestDssEvaluation->recommendation, ['Approved', 'Rejected'], true))
            ->map(function (Loan $loan): Loan {
                $loan->dss_decision_status = $loan->latestDssEvaluation->recommendation;
                $loan->dss_decision_date = $loan->latestDssEvaluation->evaluated_at;

                return $loan;
            });

        $years = $decisions->pluck('dss_decision_date')
            ->filter()
            ->map(fn ($date): int => (int) $date->format('Y'))
            ->unique()->sortDesc()->values()->all();

        $year = $filters['year'] ?? 'all';
        $status = $filters['status'] ?? 'all';
        $yearFiltered = $decisions
            ->when($year !== 'all', function (Collection $items) use ($year): Collection {
                return $items->filter(fn (Loan $loan): bool => $loan->dss_decision_date && (int) $loan->dss_decision_date->format('Y') === (int) $year);
            });
        $chartDecisions = $yearFiltered;
        $filtered = $yearFiltered
            ->when($status === 'all', fn (Collection $items): Collection => $items->whereIn('dss_decision_status', ['Approved', 'Rejected']))
            ->when($status !== 'all', fn (Collection $items): Collection => $items->where('dss_decision_status', $status))
            ->sortByDesc(fn (Loan $loan): string => $loan->dss_decision_date?->format('Y-m-d H:i:s') ?? '0000-00-00 00:00:00')
            ->values();

        $approved = $filtered->where('dss_decision_status', 'Approved')->count();
        $rejected = $filtered->where('dss_decision_status', 'Rejected')->count();
        $chartApproved = $chartDecisions->where('dss_decision_status', 'Approved')->count();
        $chartRejected = $chartDecisions->where('dss_decision_status', 'Rejected')->count();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 10;
        $paginationQuery = request()->query();
        unset($paginationQuery['year']);
        unset($paginationQuery['status']);
        $applications = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $paginationQuery],
        );

        return [
            'applications' => $applications,
            'years' => $years,
            'filters' => ['year' => $year, 'status' => $status],
            'summary' => ['approved' => $approved, 'rejected' => $rejected],
            'chart' => ['labels' => ['Approved', 'Rejected'], 'counts' => [$chartApproved, $chartRejected]],
            'undated_rejected_count' => $decisions->where('dss_decision_status', 'Rejected')->whereNull('dss_decision_date')->count(),
        ];
    }
}
