<?php

namespace Tests\Feature\Seo;

use App\Filament\Pages\SeoGoogleSettings as SeoGoogleSettingsPage;
use App\Models\SeoGoogleSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoGoogleSettingsPageTest extends TestCase
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
            ->test(SeoGoogleSettingsPage::class)
            ->fillForm([
                'client_id' => 'test-client-id.apps.googleusercontent.com',
                'client_secret' => 'test-client-secret',
            ])
            ->call('save');

        $setting = SeoGoogleSetting::current();

        $this->assertSame('test-client-id.apps.googleusercontent.com', $setting->client_id);
        $this->assertSame('test-client-secret', $setting->client_secret);
    }

    public function test_a_setting_with_no_credentials_is_reported_as_not_configured(): void
    {
        $setting = SeoGoogleSetting::current();

        $this->assertFalse($setting->isConfigured());
    }
}
