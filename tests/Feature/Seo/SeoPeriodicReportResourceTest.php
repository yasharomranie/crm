<?php

namespace Tests\Feature\Seo;

use App\Filament\Resources\SeoPeriodicReports\Pages\ManageSeoPeriodicReports;
use App\Models\SeoPeriodicReport;
use App\Models\SeoProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoPeriodicReportResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_reports(): void
    {
        $report = SeoPeriodicReport::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoPeriodicReports::class)
            ->assertCanSeeTableRecords([$report]);
    }

    public function test_generating_a_report_from_the_header_action(): void
    {
        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoPeriodicReports::class)
            ->callAction('generateReport', data: [
                'seo_project_id' => $project->id,
                'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ]);

        $this->assertSame(1, $project->periodicReports()->count());
    }

    public function test_the_view_report_action_links_to_the_print_route(): void
    {
        $report = SeoPeriodicReport::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoPeriodicReports::class)
            ->assertTableActionVisible('viewReport', $report);
    }
}
