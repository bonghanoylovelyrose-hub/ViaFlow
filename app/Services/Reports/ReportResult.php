<?php

namespace App\Services\Reports;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Complete filtered report data. Both the table and all exporters consume the
 * same rows and summary, so a download can never disagree with the screen.
 */
final class ReportResult
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{label: string, value: int|float|string|null, type: string}>  $summary
     */
    public function __construct(
        public readonly ReportDefinition $definition,
        public readonly ReportFilters $filters,
        public readonly array $rows,
        public readonly array $summary,
        public readonly int $total,
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {}

    public function paginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $this->rows,
            $this->total,
            $this->perPage,
            $this->page,
            [
                'path' => route('reports.index'),
                'query' => array_merge(['report' => $this->definition->key], $this->filters->toQuery()),
            ],
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function exportQuery(string $format): array
    {
        return array_merge([
            'report' => $this->definition->key,
            'format' => $format,
        ], $this->filters->toQuery());
    }

    public function filename(string $extension): string
    {
        return sprintf(
            'viaflow-%s-%s-%s.%s',
            $this->definition->key,
            $this->filters->filenamePeriod(),
            now()->format('Ymd-His'),
            $extension,
        );
    }
}
