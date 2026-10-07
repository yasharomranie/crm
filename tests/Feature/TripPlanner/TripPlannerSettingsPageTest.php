<?php

namespace Tests\Feature\TripPlanner;

use App\Filament\Pages\TripPlannerSettings as TripPlannerSettingsPage;
use App\Models\TripPlannerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TripPlannerSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_trip_planner_is_enabled_by_default(): void
    {
        $this->assertTrue(TripPlannerSetting::current()->is_enabled);
    }

    public function test_saving_the_form_persists_the_settings(): void
    {
        Livewire::actingAs($this->admin())
            ->test(TripPlannerSettingsPage::class)
            ->fillForm([
                'is_enabled' => false,
                'assistant_note' => 'این هفته جاده غار علی‌صدر بسته است.',
            ])
            ->call('save');

        $setting = TripPlannerSetting::current();

        $this->assertFalse($setting->is_enabled);
        $this->assertSame('این هفته جاده غار علی‌صدر بسته است.', $setting->assistant_note);
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی تریپ‌پلنر']));

        Livewire::actingAs($user)
            ->test(TripPlannerSettingsPage::class)
            ->assertForbidden();
    }
}
