<?php

namespace Tests\Feature;

use App\Filament\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\ListFormTemplates;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for the same production incident this suite has always
 * covered - form_submissions.form_template_id used to cascadeOnDelete(),
 * so deleting a FormTemplate ("فرم مادر") silently destroyed every
 * submission ever made with it. The foreign key is still restrictOnDelete()
 * at the database level - a real row deletion is refused outright while
 * any submission still references the template - but FormTemplate now
 * soft-deletes (the "سطل زباله" feature this enabled): the *ordinary*
 * delete a user reaches for day-to-day never touches that constraint at
 * all, since it only ever sets deleted_at. Only a real ForceDeleteAction
 * ("حذف کامل", from the trash) can hit the real restriction, so that's the
 * one that still needs the friendly, pre-checked notification instead of a
 * raw SQL error.
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

    public function test_soft_deleting_a_form_template_with_submissions_succeeds_and_keeps_everything_intact(): void
    {
        $template = FormTemplate::factory()->create();
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        Livewire::actingAs($this->admin())
            ->test(EditFormTemplate::class, ['record' => $template->getRouteKey()])
            ->callAction('delete');

        $this->assertTrue($template->fresh()->trashed());
        $this->assertModelExists($submission);
    }

    public function test_the_database_refuses_to_permanently_delete_a_form_template_that_has_submissions(): void
    {
        $template = FormTemplate::factory()->create();
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        $this->expectException(QueryException::class);

        $template->forceDelete();

        // Unreachable if the constraint is doing its job - left here so a
        // future accidental cascadeOnDelete() regression is caught even if
        // the exception assertion above is ever weakened.
        $this->assertModelExists($submission);
    }

    public function test_the_index_table_blocks_force_deleting_a_trashed_form_template_with_submissions(): void
    {
        $template = FormTemplate::factory()->create();
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $template->delete();

        Livewire::actingAs($this->admin())
            ->test(ListFormTemplates::class)
            ->callTableAction('forceDelete', $template)
            ->assertNotified();

        $this->assertModelExists($template);
        $this->assertTrue($template->fresh()->trashed());
        $this->assertModelExists($submission);
    }

    public function test_the_index_table_force_deletes_a_trashed_form_template_with_no_submissions(): void
    {
        $template = FormTemplate::factory()->create();
        $template->delete();

        Livewire::actingAs($this->admin())
            ->test(ListFormTemplates::class)
            ->callTableAction('forceDelete', $template);

        $this->assertModelMissing($template);
    }

    public function test_the_index_table_restores_a_trashed_form_template(): void
    {
        $template = FormTemplate::factory()->create();
        $template->delete();

        Livewire::actingAs($this->admin())
            ->test(ListFormTemplates::class)
            ->callTableAction('restore', $template);

        $this->assertFalse($template->fresh()->trashed());
    }

    public function test_a_submission_still_shows_its_form_template_after_the_template_is_trashed(): void
    {
        $template = FormTemplate::factory()->create(['title' => 'فرم آزمایشی']);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);

        $template->delete();

        $this->assertSame('فرم آزمایشی', $submission->fresh()->formTemplate->title);
    }
}
