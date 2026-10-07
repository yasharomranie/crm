<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomerAppNavSettings as CustomerAppNavSettingsPage;
use App\Models\CustomerAppNavItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAppNavSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_saving_the_form_persists_label_icon_order_and_visibility(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CustomerAppNavSettingsPage::class)
            ->fillForm([
                'items.club.label' => 'امتیازها',
                'items.club.icon' => 'heart',
                'items.club.sort_order' => 5,
                'items.club.is_visible' => false,
            ])
            ->call('save');

        $club = CustomerAppNavItem::where('key', 'club')->first();

        $this->assertSame('امتیازها', $club->label);
        $this->assertSame('heart', $club->icon);
        $this->assertSame(5, $club->sort_order);
        $this->assertFalse($club->is_visible);
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی منوی پایین']));

        Livewire::actingAs($user)
            ->test(CustomerAppNavSettingsPage::class)
            ->assertForbidden();
    }
}
