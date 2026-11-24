<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ReconnectionSchedule
{
    public const TIMEZONE = 'Asia/Manila';

    public static function parse(string $value): Carbon
    {
        return Carbon::parse($value, self::TIMEZONE);
    }

    public static function format(?Carbon $value, string $format = 'F d, Y g:i A'): ?string
    {
        return $value?->copy()->setTimezone(self::TIMEZONE)->format($format);
    }
}
