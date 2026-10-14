<?php

namespace Tests\Feature;

use App\Filament\Pages\ProductionDeploy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionDeployPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_it_shows_no_pending_deploy_and_no_log_before_any_request(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin())
            ->test(ProductionDeploy::class)
            ->assertSee('اجرای دیپلوی')
            ->assertSee('هنوز هیچ دیپلویی از این صفحه ثبت نشده است.');
    }

    public function test_requesting_a_deploy_writes_the_trigger_file_and_shows_the_pending_state(): void
    {
        Storage::fake('local');

        Livewire::actingAs($this->admin())
            ->test(ProductionDeploy::class)
            ->call('requestDeploy')
            ->assertSee('در انتظار اجرا توسط Cron Job');

        Storage::disk('local')->assertExists('deploy.trigger');
    }

    public function test_it_shows_the_last_deploy_log_once_the_cron_job_has_written_one(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('deploy_log.txt', "Already up to date.\nNothing to migrate.");

        Livewire::actingAs($this->admin())
            ->test(ProductionDeploy::class)
            ->assertSee('Already up to date.')
            ->assertSee('ساعت');
    }

    public function test_a_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی دیپلوی']));

        Livewire::actingAs($user)
            ->test(ProductionDeploy::class)
            ->assertForbidden();
    }
}
