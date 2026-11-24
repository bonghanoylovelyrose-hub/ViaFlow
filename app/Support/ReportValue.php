<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * One formatting contract for every report surface: the HTML table, PDF,
 * spreadsheet, and CSV. Values stay numeric in exports; only display strings
 * receive currency symbols or long-form dates.
 */
final class ReportValue
{
    public static function display(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($type) {
            'date' => self::date($value)?->format('M d, Y') ?? '—',
            'datetime' => self::date($value)?->format('M d, Y h:i A') ?? '—',
            'money' => 'P '.number_format((float) $value, 2),
            'number' => self::number($value),
            default => (string) $value,
        };
    }

    public static function csv(mixed $value, string $type): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = match ($type) {
            'date' => self::date($value)?->format('Y-m-d') ?? '',
            'datetime' => self::date($value)?->format('Y-m-d H:i:s') ?? '',
            'money' => number_format((float) $value, 2, '.', ''),
            'number' => is_numeric($value) ? (string) $value : (string) $value,
            default => (string) $value,
        };

        // Prevent spreadsheet formula injection in free-text fields. Numeric
        // exports must remain numeric, including negative values.
        if (in_array($type, ['text', 'long_text', 'status'], true)
            && preg_match('/^[=+\-@]/', ltrim($formatted)) === 1) {
            return "'".$formatted;
        }

        return $formatted;
    }

    public static function excel(mixed $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($type) {
            'date' => self::date($value)?->format('Y-m-d') ?? '',
            'datetime' => self::date($value)?->format('Y-m-d H:i') ?? '',
            'money', 'number' => is_numeric($value) ? (float) $value : (string) $value,
            default => (string) $value,
        };
    }

    public static function number(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        $number = (float) $value;

        return floor($number) === $number
            ? number_format($number, 0)
            : number_format($number, 2);
    }

    private static function date(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }
}
