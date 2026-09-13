<?php

namespace Tests\Feature;

use App\Enums\DocumentAccessLevel;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_department_can_belong_to_a_company_and_the_relationship_works_both_ways(): void
    {
        $company = Company::factory()->create(['name' => 'گنج‌نامه']);
        $department = Department::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($department->company->is($company));
        $this->assertTrue($company->departments->contains($department));
    }

    public function test_a_document_resolves_its_company_through_its_department(): void
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $document = Document::factory()->create(['department_id' => $department->id]);

        $documentWithoutDepartment = Document::factory()->create(['department_id' => null]);

        $this->assertTrue($document->company()->is($company));
        $this->assertNull($documentWithoutDepartment->company());
    }

    public function test_a_project_resolves_its_company_through_its_department(): void
    {
        $company = Company::factory()->create();
        $department = Department::factory()->create(['company_id' => $company->id]);
        $project = Project::create(['code' => 'PRJ-1', 'title' => 'پروژهٔ نمونه', 'department_id' => $department->id]);

        $this->assertTrue($project->company()->is($company));
    }

    public function test_access_control_sync_creates_permissions_for_the_companies_module(): void
    {
        $this->artisan('access-control:sync');

        $this->assertTrue(Permission::where('name', 'companies.view_any')->exists());
        $this->assertFalse(Permission::where('name', 'contract-companies.view_any')->exists());
    }

    public function test_documents_table_can_be_filtered_by_company(): void
    {
        $companyA = Company::factory()->create(['name' => 'گنج‌نامه']);
        $companyB = Company::factory()->create(['name' => 'زاگرس']);
        $departmentA = Department::factory()->create(['company_id' => $companyA->id]);
        $departmentB = Department::factory()->create(['company_id' => $companyB->id]);

        $documentA = Document::factory()->create([
            'department_id' => $departmentA->id,
            'access_level' => DocumentAccessLevel::PublicInternal,
        ]);
        $documentB = Document::factory()->create([
            'department_id' => $departmentB->id,
            'access_level' => DocumentAccessLevel::PublicInternal,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ListDocuments::class)
            ->filterTable('company', $companyA->id)
            ->assertCanSeeTableRecords([$documentA])
            ->assertCanNotSeeTableRecords([$documentB]);
    }

    public function test_projects_table_can_be_filtered_by_company(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $departmentA = Department::factory()->create(['company_id' => $companyA->id]);
        $departmentB = Department::factory()->create(['company_id' => $companyB->id]);

        $projectA = Project::create(['code' => 'PRJ-A', 'title' => 'پروژهٔ الف', 'department_id' => $departmentA->id]);
        $projectB = Project::create(['code' => 'PRJ-B', 'title' => 'پروژهٔ ب', 'department_id' => $departmentB->id]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ListProjects::class)
            ->filterTable('company', $companyA->id)
            ->assertCanSeeTableRecords([$projectA])
            ->assertCanNotSeeTableRecords([$projectB]);
    }
}
