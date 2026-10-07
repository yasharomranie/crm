<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomerAppAuthSettings as CustomerAppAuthSettingsPage;
use App\Models\CustomerAppAuthSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAppAuthSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_all_three_sign_in_methods_are_enabled_by_default(): void
    {
        $setting = CustomerAppAuthSetting::current();

        $this->assertTrue($setting->otp_enabled);
        $this->assertTrue($setting->password_enabled);
        $this->assertTrue($setting->google_enabled);
    }

    public function test_saving_the_form_persists_the_toggles(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CustomerAppAuthSettingsPage::class)
            ->fillForm([
                'otp_enabled' => false,
                'password_enabled' => true,
                'google_enabled' => false,
            ])
            ->call('save');

        $setting = CustomerAppAuthSetting::current();

        $this->assertFalse($setting->otp_enabled);
        $this->assertTrue($setting->password_enabled);
        $this->assertFalse($setting->google_enabled);
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی روش‌های ورود']));

        Livewire::actingAs($user)
            ->test(CustomerAppAuthSettingsPage::class)
            ->assertForbidden();
    }
}
