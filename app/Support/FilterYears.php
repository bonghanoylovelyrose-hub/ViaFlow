<?php

namespace App\Support;

final class FilterYears
{
    private const FIRST_YEAR = 2014;

    private const YEARS_AHEAD = 3;

    /** @return list<int> */
    public static function available(iterable $recordYears): array
    {
        $latestRecordYear = collect($recordYears)
            ->map(fn ($year) => (int) $year)
            ->filter(fn (int $year) => $year >= self::FIRST_YEAR && $year <= 9999)
            ->max() ?? self::FIRST_YEAR;
        $latestYear = min(9999, max((int) now()->year + self::YEARS_AHEAD, $latestRecordYear));

        return range($latestYear, self::FIRST_YEAR);
    }
}
