<?php

namespace Tests\Feature\Seo;

use App\Filament\Pages\SeoAlertSettings as SeoAlertSettingsPage;
use App\Models\SeoAlertSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoAlertSettingsPageTest extends TestCase
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
        $recipient = User::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(SeoAlertSettingsPage::class)
            ->fillForm([
                'is_enabled' => true,
                'rank_drop_threshold' => 7,
                'notify_push' => false,
                'notify_sms' => true,
                'notify_email' => false,
                'recipient_user_ids' => [$recipient->id],
            ])
            ->call('save');

        $setting = SeoAlertSetting::current();

        $this->assertTrue($setting->is_enabled);
        $this->assertSame(7, $setting->rank_drop_threshold);
        $this->assertFalse($setting->notify_push);
        $this->assertTrue($setting->notify_sms);
        $this->assertFalse($setting->notify_email);
        $this->assertSame([$recipient->id], $setting->recipient_user_ids);
    }

    public function test_the_default_setting_is_disabled(): void
    {
        $setting = SeoAlertSetting::current();

        $this->assertFalse($setting->is_enabled);
    }
}
