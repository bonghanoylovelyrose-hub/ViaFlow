<?php

namespace App\Services\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Validated report filters shared by the on-screen report and every export.
 */
final class ReportFilters
{
    public function __construct(
        public readonly ?int $month = null,
        public readonly ?int $year = null,
        public readonly ?string $status = null,
        public readonly ?string $category = null,
        public readonly ?string $channel = null,
        public readonly ?string $type = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            month: filled($input['month'] ?? null) ? (int) $input['month'] : null,
            year: filled($input['year'] ?? null) ? (int) $input['year'] : null,
            status: filled($input['status'] ?? null) ? (string) $input['status'] : null,
            category: filled($input['category'] ?? null) ? (string) $input['category'] : null,
            channel: filled($input['channel'] ?? null) ? (string) $input['channel'] : null,
            type: filled($input['type'] ?? null) ? (string) $input['type'] : null,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toQuery(): array
    {
        // Keep an explicit empty period in every generated link/export URL.
        // Without it, the reports controller cannot distinguish "All Months"
        // from "period not supplied" and falls back to the current month.
        return [
            'month' => $this->month ?? '',
            'year' => $this->year ?? '',
            ...array_filter([
                'status' => $this->status,
                'category' => $this->category,
                'channel' => $this->channel,
                'type' => $this->type,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];
    }

    public function periodLabel(): string
    {
        if ($this->month !== null && $this->year !== null) {
            return Carbon::create($this->year, $this->month, 1)->format('F Y');
        }

        if ($this->month !== null) {
            return Carbon::create(now()->year, $this->month, 1)->format('F');
        }

        if ($this->year !== null) {
            return (string) $this->year;
        }

        return 'All time';
    }

    public function filenamePeriod(): string
    {
        if ($this->month !== null && $this->year !== null) {
            return sprintf('%04d-%02d', $this->year, $this->month);
        }

        if ($this->year !== null) {
            return (string) $this->year;
        }

        if ($this->month !== null) {
            return sprintf('month-%02d', $this->month);
        }

        return 'all-time';
    }

    /**
     * Applies the selected month/year to a report's declared date column.
     */
    public function applyPeriod(Builder $query, string $column): Builder
    {
        if ($this->year !== null) {
            $query->whereYear($column, $this->year);
        }

        if ($this->month !== null) {
            $query->whereMonth($column, $this->month);
        }

        return $query;
    }

    /**
     * End of the selected accounting period, used by balance snapshots.
     */
    public function periodCutoff(): ?Carbon
    {
        if ($this->year === null && $this->month === null) {
            return null;
        }

        // A month without a year is still a concrete balance-sheet cutoff;
        // use the current year rather than silently ignoring the month.
        $date = Carbon::create($this->year ?? now()->year, $this->month ?? 12, 1);

        return $this->month === null
            ? $date->endOfYear()
            : $date->endOfMonth();
    }

    /**
     * @return array<string, string>
     */
    public function appliedFilterLabels(): array
    {
        $labels = ['Period' => $this->periodLabel()];

        if ($this->status !== null) {
            $labels['Status'] = $this->status;
        }

        if ($this->category !== null) {
            $labels['Category'] = $this->category;
        }

        if ($this->channel !== null) {
            $labels['Channel'] = $this->channel;
        }

        if ($this->type !== null) {
            $labels['Type'] = $this->type;
        }

        return $labels;
    }
}
