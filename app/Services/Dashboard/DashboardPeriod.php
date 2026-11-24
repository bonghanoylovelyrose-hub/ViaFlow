<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Carbon;

/**
 * The month/year period selected on the admin dashboard.
 *
 * A null month means "All Months" for the selected year. Period-based metrics
 * use the full year in that case, while current snapshots are labelled as such.
 */
final class DashboardPeriod
{
    public function __construct(
        public readonly ?int $month,
        public readonly int $year,
        public readonly bool $allAvailable = false,
    ) {}

    public static function current(): self
    {
        return new self(now()->month, now()->year);
    }

    public static function allAvailable(): self
    {
        return new self(null, (int) now()->year, true);
    }

    public function start(): Carbon
    {
        return Carbon::create($this->year, $this->month ?? 1, 1)->startOfDay();
    }

    public function end(): Carbon
    {
        if ($this->allAvailable) {
            return now();
        }

        return $this->month === null
            ? Carbon::create($this->year, 12, 31)->endOfDay()
            : Carbon::create($this->year, $this->month, 1)->endOfMonth()->endOfDay();
    }

    public function previous(): self
    {
        if ($this->month === null) {
            return new self(null, $this->year - 1);
        }

        if ($this->month === 1) {
            return new self(12, $this->year - 1);
        }

        return new self($this->month - 1, $this->year);
    }

    public function isAllMonths(): bool
    {
        return $this->month === null;
    }

    public function label(): string
    {
        if ($this->allAvailable) {
            return 'All available records';
        }

        return $this->month === null
            ? $this->year.' (All Months)'
            : Carbon::create($this->year, $this->month, 1)->format('F Y');
    }

    public function shortLabel(): string
    {
        return $this->month === null
            ? (string) $this->year
            : Carbon::create($this->year, $this->month, 1)->format('M Y');
    }

    public function previousLabel(): string
    {
        if ($this->allAvailable) {
            return 'No prior period';
        }

        return $this->previous()->label();
    }

    public function endLabel(): string
    {
        return $this->end()->format('M d, Y');
    }

    /**
     * Explicit period parameters for links into the Reports Center. Empty
     * values are intentional: they preserve "All Months" instead of allowing
     * the reports controller to fall back to the current month.
     *
     * @return array{month: int|string, year: int}
     */
    public function toQuery(): array
    {
        if ($this->allAvailable) {
            return ['month' => '', 'year' => ''];
        }

        return [
            'month' => $this->month ?? '',
            'year' => $this->year,
        ];
    }

    /**
     * @return list<Carbon>
     */
    public function months(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month): Carbon => Carbon::create($this->year, $month, 1))
            ->all();
    }

    public function selectedMonthIndex(): ?int
    {
        return $this->month === null ? null : $this->month - 1;
    }
}
