<?php

namespace Tests\Feature;

use App\Filament\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for the other half of the same production incident:
 * form_submissions.form_template_id used to cascadeOnDelete(), so deleting
 * an entire FormTemplate ("فرم مادر") silently destroyed every submission
 * ever made with it - not just its fields. The foreign key is now
 * restrictOnDelete(): the database refuses the delete outright while any
 * submission still references the template. EditFormTemplate checks this
 * up front and shows a friendly notification instead of a raw SQL error.
 */
class FormTemplateDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_database_refuses_to_delete_a_form_template_that_has_submissions(): void
    {
        $template = FormTemplate::factory()->create();
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        $this->expectException(QueryException::class);

        $template->delete();

        // Unreachable if the constraint is doing its job - left here so a
        // future accidental cascadeOnDelete() regression is caught even if
        // the exception assertion above is ever weakened.
        $this->assertModelExists($submission);
    }

    public function test_the_edit_page_blocks_deleting_a_form_template_with_submissions_and_keeps_it_intact(): void
    {
        $template = FormTemplate::factory()->create();
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        Livewire::actingAs($this->admin())
            ->test(EditFormTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertModelExists($template);
        $this->assertModelExists($submission);
    }

    public function test_the_edit_page_deletes_a_form_template_with_no_submissions(): void
    {
        $template = FormTemplate::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(EditFormTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($template);
    }
}
