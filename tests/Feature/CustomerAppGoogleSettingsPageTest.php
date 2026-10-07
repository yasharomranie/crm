<?php

namespace Tests\Feature;

use App\Filament\Pages\CustomerAppGoogleSettings as CustomerAppGoogleSettingsPage;
use App\Models\CustomerAppGoogleSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerAppGoogleSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_saving_the_form_persists_the_singleton_google_setting_row(): void
    {
        Livewire::actingAs($this->admin())
            ->test(CustomerAppGoogleSettingsPage::class)
            ->fillForm([
                'client_id' => 'test-client-id.apps.googleusercontent.com',
                'client_secret' => 'test-client-secret',
            ])
            ->call('save');

        $setting = CustomerAppGoogleSetting::current();

        $this->assertSame('test-client-id.apps.googleusercontent.com', $setting->client_id);
        $this->assertSame('test-client-secret', $setting->client_secret);
    }

    public function test_a_setting_with_no_credentials_is_reported_as_not_configured(): void
    {
        $setting = CustomerAppGoogleSetting::current();

        $this->assertFalse($setting->isConfigured());
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی ورود با گوگل']));

        Livewire::actingAs($user)
            ->test(CustomerAppGoogleSettingsPage::class)
            ->assertForbidden();
    }
}
