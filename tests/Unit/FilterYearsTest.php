<?php

namespace Tests\Unit;

use App\Support\FilterYears;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FilterYearsTest extends TestCase
{
    public function test_available_years_roll_forward_with_the_calendar(): void
    {
        $this->travelTo(Carbon::parse('2026-12-31'));
        $this->assertSame(range(2029, 2014), FilterYears::available([]));

        $this->travelTo(Carbon::parse('2027-01-01'));
        $this->assertSame(range(2030, 2014), FilterYears::available([]));
        $this->assertSame(range(2037, 2014), FilterYears::available(['2037', '2029', '2029']));
    }
}
