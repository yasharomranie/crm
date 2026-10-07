<?php

namespace Tests\Feature;

use App\Models\CustomerAppNavItem;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppNavItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_defaults_creates_the_four_fixed_items(): void
    {
        CustomerAppNavItem::ensureDefaults();

        $keys = CustomerAppNavItem::query()->pluck('key')->sort()->values()->all();

        $this->assertSame(['bookings', 'club', 'home', 'wallet'], $keys);
    }

    public function test_ensure_defaults_never_overwrites_an_existing_edit(): void
    {
        CustomerAppNavItem::ensureDefaults();
        CustomerAppNavItem::where('key', 'home')->update(['label' => 'عنوان سفارشی']);

        CustomerAppNavItem::ensureDefaults();

        $this->assertSame('عنوان سفارشی', CustomerAppNavItem::where('key', 'home')->value('label'));
    }

    public function test_for_display_only_includes_visible_items_in_sort_order(): void
    {
        CustomerAppNavItem::ensureDefaults();
        CustomerAppNavItem::where('key', 'club')->update(['is_visible' => false]);

        $items = CustomerAppNavItem::forDisplay(null, 'customer-app');

        $this->assertSame(['home', 'bookings', 'wallet'], $items->pluck('key')->all());
    }

    public function test_for_display_sends_a_guest_requiring_tab_to_login_when_signed_out(): void
    {
        $items = CustomerAppNavItem::forDisplay(null, 'customer-app');

        $bookings = $items->firstWhere('key', 'bookings');

        $this->assertSame(route('customer-app.auth.login'), $bookings['url']);
        $this->assertFalse($bookings['active']);
    }

    public function test_for_display_sends_a_signed_in_guest_to_the_real_destination(): void
    {
        $guest = Guest::factory()->create();

        $items = CustomerAppNavItem::forDisplay($guest, 'customer-app.bookings');

        $bookings = $items->firstWhere('key', 'bookings');

        $this->assertSame(route('customer-app.bookings'), $bookings['url']);
        $this->assertTrue($bookings['active']);
    }

    public function test_for_display_marks_the_home_tab_active_without_requiring_login(): void
    {
        $items = CustomerAppNavItem::forDisplay(null, 'customer-app');

        $home = $items->firstWhere('key', 'home');

        $this->assertSame(route('customer-app'), $home['url']);
        $this->assertTrue($home['active']);
    }

    public function test_relabeling_a_tab_is_reflected_in_for_display(): void
    {
        CustomerAppNavItem::ensureDefaults();
        CustomerAppNavItem::where('key', 'wallet')->update(['label' => 'موجودی من']);

        $items = CustomerAppNavItem::forDisplay(null, 'customer-app');

        $this->assertSame('موجودی من', $items->firstWhere('key', 'wallet')['label']);
    }
}
