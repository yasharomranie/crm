<?php

namespace Tests\Feature;

use App\Enums\ChecklistRecurrence;
use App\Enums\TaskType;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Models\FormTemplate;
use App\Models\Task;
use App\Models\TaskChecklistTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChecklistFormLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_checklist_template_can_be_linked_to_a_form_template(): void
    {
        $template = FormTemplate::factory()->create();

        $checklist = TaskChecklistTemplate::create([
            'name' => 'چک‌لیست نظافت اتاق',
            'recurrence' => ChecklistRecurrence::Daily,
            'is_active' => true,
            'form_template_id' => $template->id,
        ]);

        $this->assertTrue($checklist->formTemplate->is($template));
    }

    public function test_the_complete_with_form_action_is_visible_only_when_a_form_is_linked(): void
    {
        $template = FormTemplate::factory()->create();

        $checklistWithForm = TaskChecklistTemplate::create([
            'name' => 'چک‌لیست با فرم',
            'recurrence' => ChecklistRecurrence::Daily,
            'is_active' => true,
            'form_template_id' => $template->id,
        ]);

        $checklistWithoutForm = TaskChecklistTemplate::create([
            'name' => 'چک‌لیست بدون فرم',
            'recurrence' => ChecklistRecurrence::Daily,
            'is_active' => true,
        ]);

        $taskWithForm = Task::factory()->create([
            'task_type' => TaskType::ChecklistGenerated,
            'source_id' => $checklistWithForm->id,
            'source_type' => TaskChecklistTemplate::class,
        ]);

        $taskWithoutForm = Task::factory()->create([
            'task_type' => TaskType::ChecklistGenerated,
            'source_id' => $checklistWithoutForm->id,
            'source_type' => TaskChecklistTemplate::class,
        ]);

        $plainTask = Task::factory()->create();

        $this->actingAs(User::factory()->create());

        Livewire::test(ListTasks::class)
            ->assertTableActionVisible('completeWithForm', record: $taskWithForm)
            ->assertTableActionHidden('completeWithForm', record: $taskWithoutForm)
            ->assertTableActionHidden('completeWithForm', record: $plainTask);
    }

    public function test_the_complete_with_form_action_links_to_the_linked_form_templates_create_page(): void
    {
        $template = FormTemplate::factory()->create();

        $checklist = TaskChecklistTemplate::create([
            'name' => 'چک‌لیست با فرم',
            'recurrence' => ChecklistRecurrence::Daily,
            'is_active' => true,
            'form_template_id' => $template->id,
        ]);

        $task = Task::factory()->create([
            'task_type' => TaskType::ChecklistGenerated,
            'source_id' => $checklist->id,
            'source_type' => TaskChecklistTemplate::class,
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ListTasks::class)
            ->assertTableActionHasUrl('completeWithForm', route('filament.admin.resources.form-submissions.create')."?form_template_id={$template->id}", record: $task);
    }
}
