<?php

namespace Tests\Feature\Seo;

use App\Models\SeoPeriodicReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoPeriodicReportPrintControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_view_permission_can_open_the_report(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        $report = SeoPeriodicReport::factory()->create(['html' => '<html><body>گزارش آزمایشی</body></html>']);

        $response = $this->actingAs($admin)->get(route('seo-periodic-reports.print', $report));

        $response->assertOk();
        $response->assertSee('گزارش آزمایشی', escape: false);
    }

    /**
     * A user with an assigned role (but not the seo-periodic-reports.view
     * permission) is denied - mirrors ContractAssistantTest's own
     * "بدون دسترسی قرارداد" pattern. A user with *no* role at all keeps
     * unrestricted access by this app's own documented backward-compatible
     * default (see AppServiceProvider::registerAccessControl()'s
     * Gate::before), so that's not a useful "forbidden" fixture.
     */
    public function test_a_user_without_the_view_permission_is_forbidden(): void
    {
        $role = Role::create(['name' => 'بدون دسترسی گزارش سئو']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $report = SeoPeriodicReport::factory()->create();

        $response = $this->actingAs($user)->get(route('seo-periodic-reports.print', $report));

        $response->assertForbidden();
    }
}
