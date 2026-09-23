<?php

namespace Tests\Feature;

use App\Filament\Pages\SmsSettings as SmsSettingsPage;
use App\Models\SmsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmsSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_saving_the_form_persists_the_singleton_sms_setting_row(): void
    {
        Livewire::actingAs($this->admin())
            ->test(SmsSettingsPage::class)
            ->fillForm([
                'is_enabled' => true,
                'api_key' => 'test-api-key',
                'line_number' => '3000123456',
            ])
            ->call('save');

        $setting = SmsSetting::current();

        $this->assertTrue($setting->is_enabled);
        $this->assertSame('test-api-key', $setting->api_key);
        $this->assertSame('3000123456', $setting->line_number);
    }

    public function test_a_setting_disabled_with_no_api_key_is_reported_as_not_usable(): void
    {
        $setting = SmsSetting::current();

        $this->assertFalse($setting->isUsable());
    }
}
