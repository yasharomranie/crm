<?php

namespace Tests\Feature;

use App\Filament\Pages\WhatsAppSettings;
use App\Models\AiAssistantProfile;
use App\Models\User;
use App\Models\WhatsAppBusinessSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSettingsUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر اتصال واتس‌اپ']);
        $role->givePermissionTo(Permission::where('name', 'whatsapp-settings.access')->sole());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_saving_the_form_persists_the_singleton_setting(): void
    {
        $this->actingAsSettingsUser();
        $profile = AiAssistantProfile::factory()->create();

        Livewire::test(WhatsAppSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'ai_assistant_profile_id' => $profile->id,
                'phone_number_id' => '1234567890',
                'access_token' => 'secret-token',
                'verify_token' => 'my-verify-token',
            ])
            ->call('save');

        $setting = WhatsAppBusinessSetting::current();
        $this->assertTrue($setting->is_enabled);
        $this->assertSame($profile->id, $setting->ai_assistant_profile_id);
        $this->assertSame('1234567890', $setting->phone_number_id);
        $this->assertSame('secret-token', $setting->access_token);
        $this->assertTrue($setting->isUsable());
    }

    public function test_a_user_without_the_permission_is_denied(): void
    {
        $this->artisan('access-control:sync');
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی واتس‌اپ']));

        $this->actingAs($user)
            ->get(route('filament.admin.pages.whats-app-settings'))
            ->assertForbidden();
    }
}
