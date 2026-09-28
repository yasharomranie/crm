<?php

namespace Tests\Feature\Seo;

use App\Filament\Pages\SeoRankTrackerSettings as SeoRankTrackerSettingsPage;
use App\Models\SeoRankTrackerSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoRankTrackerSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_saving_the_form_persists_the_singleton_setting_row(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SeoRankTrackerSettingsPage::class)
            ->fillForm([
                'api_key' => 'test-api-key',
                'search_engine_id' => 'test-cx',
            ])
            ->call('save');

        $setting = SeoRankTrackerSetting::current();

        $this->assertSame('test-api-key', $setting->api_key);
        $this->assertSame('test-cx', $setting->search_engine_id);
    }

    public function test_a_setting_with_no_credentials_is_reported_as_not_configured(): void
    {
        $setting = SeoRankTrackerSetting::current();

        $this->assertFalse($setting->isConfigured());
    }
}
