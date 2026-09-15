<?php

namespace Tests\Feature;

use Illuminate\Support\Number;
use Tests\TestCase;

/**
 * The whole app shows numbers in Persian digits, never Western ones - this
 * locks in the one shared conversion point
 * (AppServiceProvider::registerPersianNumberMacro()) that stat widgets,
 * charts, and print views rely on, plus the app-wide Number::useLocale()
 * default that makes bare Number::format() calls Persian too.
 */
class PersianNumberMacroTest extends TestCase
{
    public function test_integers_and_floats_can_be_converted_to_persian_digits(): void
    {
        $this->assertSame('۱۲۳۴', Number::toPersian(1234));
        $this->assertSame('۴۲.۵', Number::toPersian(42.5));
    }

    public function test_it_only_swaps_digit_characters_inside_an_already_composed_string(): void
    {
        $this->assertSame('۱۴ روز بدون پیگیری', Number::toPersian('14 روز بدون پیگیری'));
        $this->assertSame('۷۸ ساعت و ۲ دقیقه', Number::toPersian('78 ساعت و 2 دقیقه'));
    }

    public function test_number_format_without_an_explicit_locale_defaults_to_persian(): void
    {
        $this->assertSame('۱٬۲۳۴٬۵۶۷', Number::format(1234567));
    }
}
