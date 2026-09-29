<?php

namespace Tests\Feature;

use App\Filament\Pages\WebAppDomainSettings;
use App\Models\User;
use App\Models\WebAppDomainSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebAppDomainSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_singleton_setting_defaults_to_disabled_and_unverified(): void
    {
        $setting = WebAppDomainSetting::current();

        $this->assertFalse($setting->is_enabled);
        $this->assertNull($setting->domain);
        $this->assertNull($setting->dns_verified_at);
    }

    public function test_saving_the_form_persists_the_singleton_setting_row(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WebAppDomainSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'domain' => 'app.example.com',
            ])
            ->call('save');

        $setting = WebAppDomainSetting::current();

        $this->assertTrue($setting->is_enabled);
        $this->assertSame('app.example.com', $setting->domain);
    }

    public function test_changing_the_domain_clears_any_previous_dns_verification(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'old.example.com',
            'is_enabled' => true,
            'dns_verified_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WebAppDomainSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'domain' => 'new.example.com',
            ])
            ->call('save');

        $setting = WebAppDomainSetting::current();

        $this->assertSame('new.example.com', $setting->domain);
        $this->assertNull($setting->dns_verified_at);
    }

    public function test_saving_the_same_domain_again_does_not_clear_its_verification(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'app.example.com',
            'is_enabled' => true,
            'dns_verified_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WebAppDomainSettings::class)
            ->fillForm([
                'is_enabled' => false,
                'domain' => 'app.example.com',
            ])
            ->call('save');

        $setting = WebAppDomainSetting::current();

        $this->assertFalse($setting->is_enabled);
        $this->assertNotNull($setting->dns_verified_at);
    }

    public function test_verify_dns_action_marks_an_unresolvable_domain_as_unverified(): void
    {
        WebAppDomainSetting::current()->update(['domain' => 'this-domain-does-not-exist.invalid']);

        Livewire::actingAs($this->admin())
            ->test(WebAppDomainSettings::class)
            ->callAction('verifyDns');

        $this->assertNull(WebAppDomainSetting::current()->dns_verified_at);
    }

    public function test_the_help_action_renders_the_full_guide_with_the_real_dns_target(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WebAppDomainSettings::class)
            ->mountAction('help')
            ->assertSee(parse_url(config('app.url'), PHP_URL_HOST));
    }

    public function test_view_app_points_at_the_plain_app_path_when_no_domain_is_connected(): void
    {
        Livewire::actingAs($this->admin())
            ->test(WebAppDomainSettings::class)
            ->assertActionHasUrl('viewApp', route('customer-app'));
    }

    public function test_view_app_points_at_the_custom_domain_once_it_is_enabled_and_verified(): void
    {
        WebAppDomainSetting::current()->update([
            'domain' => 'app.example.com',
            'is_enabled' => true,
            'dns_verified_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(WebAppDomainSettings::class)
            ->assertActionHasUrl('viewApp', 'https://app.example.com/');
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی دامنه اختصاصی']));

        Livewire::actingAs($user)
            ->test(WebAppDomainSettings::class)
            ->assertForbidden();
    }
}
