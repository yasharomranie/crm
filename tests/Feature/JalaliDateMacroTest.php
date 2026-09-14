<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The whole app shows dates in the Jalali (Shamsi) calendar, never
 * Gregorian - this locks in the one shared conversion point
 * (AppServiceProvider::registerJalaliDateMacro()) that every print view,
 * export, and dashboard blade relies on.
 */
class JalaliDateMacroTest extends TestCase
{
    public function test_carbon_instances_can_be_formatted_as_a_jalali_date(): void
    {
        $date = Carbon::parse('2026-09-14 08:05:00');

        $this->assertSame('۱۴۰۵/۰۶/۲۳', $date->toJalali('Y/m/d'));
        $this->assertSame('۱۴۰۵/۰۶/۲۳ ۰۸:۰۵', $date->toJalali('Y/m/d H:i'));
    }

    public function test_the_default_format_is_a_full_date_and_time(): void
    {
        $date = Carbon::parse('2024-03-20 12:00:00');

        $this->assertSame('۱۴۰۳/۰۱/۰۱ ۱۲:۰۰', $date->toJalali());
    }
}
