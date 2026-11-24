<?php

namespace App\Http\Controllers;

use App\Services\Reports\Exporters\CsvReportExporter;
use App\Services\Reports\Exporters\PdfReportExporter;
use App\Services\Reports\Exporters\XlsxReportExporter;
use App\Services\Reports\ReportDefinition;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $validated = Validator::make($this->filterInput($request), [
            'report' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,9999'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $reportKey = $reports->resolveKey($validated['report'] ?? null);
        $filters = $this->filtersFor($reports->definition($reportKey), $validated);
        $result = $reports->paginate(
            $reportKey,
            $filters,
            (int) ($validated['page'] ?? 1),
        );

        return view('admin.reports.index', [
            'result' => $result,
            'filters' => $filters,
            'reportKey' => $reportKey,
            'definitions' => $reports->definitions(),
            'groupedDefinitions' => $reports->groupedDefinitions(),
            'years' => $reports->years(),
        ]);
    }

    public function export(
        Request $request,
        ReportService $reports,
        CsvReportExporter $csv,
        XlsxReportExporter $xlsx,
        PdfReportExporter $pdf,
    ): Response {
        $validated = Validator::make($this->filterInput($request), [
            'report' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,9999'],
            'status' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:100'],
            'format' => ['required', 'in:csv,xlsx,pdf'],
        ])->validate();

        $reportKey = $reports->resolveKey($validated['report'] ?? null);

        $result = $reports->export(
            $reportKey,
            $this->filtersFor($reports->definition($reportKey), $validated),
        );

        return match ($validated['format']) {
            'xlsx' => $xlsx->download($result),
            'pdf' => $pdf->download($result),
            default => $csv->download($result),
        };
    }

    /**
     * The old export link used arbitrary start/end dates. Keep those links
     * working by translating the start date into the new month/year filters.
     *
     * @return array<string, mixed>
     */
    private function filterInput(Request $request): array
    {
        $input = $request->only([
            'report',
            'report_type',
            'month',
            'year',
            'status',
            'category',
            'channel',
            'type',
            'page',
            'format',
            'start_date',
        ]);

        $input['report'] = $input['report'] ?? $input['report_type'] ?? 'collections';

        $hasMonth = $request->has('month');
        $hasYear = $request->has('year');

        if (! $hasMonth && ! $hasYear) {
            if (filled($input['start_date'] ?? null)) {
                try {
                    $start = Carbon::parse($input['start_date']);
                    $input['month'] = $start->month;
                    $input['year'] = $start->year;
                } catch (\Throwable) {
                    // Ignore malformed legacy dates and fall through to defaults.
                }
            }

            if (blank($input['month'] ?? null)) {
                $input['month'] = now()->month;
            }
        } elseif (! $hasMonth) {
            // A supplied year without a month means "All Months" for that year.
            $input['month'] = '';
        }

        unset($input['start_date']);

        return $input;
    }

    /**
     * Drop secondary filters that do not exist on the selected report. This
     * keeps a status chosen on one report from silently emptying another when
     * the user switches reports.
     *
     * @param  array<string, mixed>  $validated
     */
    private function filtersFor(ReportDefinition $definition, array $validated): ReportFilters
    {
        return new ReportFilters(
            month: filled($validated['month'] ?? null) ? (int) $validated['month'] : null,
            year: filled($validated['year'] ?? null) ? (int) $validated['year'] : null,
            status: in_array($validated['status'] ?? null, $definition->statusOptions, true)
                ? (string) $validated['status']
                : null,
            category: in_array($validated['category'] ?? null, $definition->categoryOptions, true)
                ? (string) $validated['category']
                : null,
            channel: in_array($validated['channel'] ?? null, $definition->channelOptions, true)
                ? (string) $validated['channel']
                : null,
            type: in_array($validated['type'] ?? null, $definition->typeOptions, true)
                ? (string) $validated['type']
                : null,
        );
    }
}
