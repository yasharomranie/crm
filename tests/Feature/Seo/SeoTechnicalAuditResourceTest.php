<?php

namespace Tests\Feature\Seo;

use App\Filament\Resources\SeoTechnicalAudits\Pages\ManageSeoTechnicalAudits;
use App\Jobs\RunTechnicalSeoAuditJob;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTechnicalAuditIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeoTechnicalAuditResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_index_page_lists_audits(): void
    {
        $audit = SeoTechnicalAudit::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoTechnicalAudits::class)
            ->assertCanSeeTableRecords([$audit]);
    }

    public function test_running_a_new_audit_dispatches_the_job(): void
    {
        Bus::fake();

        $project = SeoProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageSeoTechnicalAudits::class)
            ->callAction('runAudit', data: ['seo_project_id' => $project->id]);

        Bus::assertDispatched(RunTechnicalSeoAuditJob::class, fn (RunTechnicalSeoAuditJob $job) => $job->seoProjectId === $project->id);
    }

    public function test_the_view_issues_action_is_available(): void
    {
        $audit = SeoTechnicalAudit::factory()->create();
        SeoTechnicalAuditIssue::factory()->create(['seo_technical_audit_id' => $audit->id]);

        Livewire::actingAs($this->admin())
            ->test(ManageSeoTechnicalAudits::class)
            ->assertTableActionVisible('viewIssues', $audit);
    }
}
