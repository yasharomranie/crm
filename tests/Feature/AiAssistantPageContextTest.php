<?php

namespace Tests\Feature;

use App\Enums\FormFieldType;
use App\Livewire\AiAssistantWidget;
use App\Models\AiProvider;
use App\Models\FormField;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The floating assistant reports the URL it is open on; the engine turns it
 * into a "current page" note in the system prompt and, on a form template's
 * own edit page, defaults the form-editing tools to that form.
 */
class AiAssistantPageContextTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFormEditor(array $extraPermissions = []): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'ویرایشگر فرم']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act',
            'form-templates.view_any', 'form-templates.view', 'form-templates.update',
            ...$extraPermissions,
        ])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fakeToolCallThenAnswer(string $toolName, array $arguments): void
    {
        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
                ]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'باشه.']]]]);
    }

    private function fakeSimpleAnswer(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام']]]])]);
    }

    private function systemPromptOfFirstRequest(): string
    {
        $recorded = Http::recorded()->first()[0];

        return collect($recorded->data()['messages'])->firstWhere('role', 'system')['content'];
    }

    public function test_system_prompt_names_the_form_being_edited(): void
    {
        AiProvider::factory()->active()->create();
        $this->actingAsFormEditor();
        $template = FormTemplate::factory()->create(['title' => 'قرارداد اجارهٔ ویلا']);
        $this->fakeSimpleAnswer();

        Livewire::test(AiAssistantWidget::class)
            ->set('body', 'یه فیلد اضافه کن')
            ->call('send', "/admin/form-templates/{$template->id}/edit");

        $prompt = $this->systemPromptOfFirstRequest();

        $this->assertStringContainsString('قرارداد اجارهٔ ویلا', $prompt);
        $this->assertStringContainsString('موقعیت فعلی کاربر', $prompt);
    }

    public function test_no_context_note_is_added_without_a_page_url(): void
    {
        AiProvider::factory()->active()->create();
        $this->actingAsFormEditor();
        $this->fakeSimpleAnswer();

        Livewire::test(AiAssistantWidget::class)
            ->set('body', 'سلام')
            ->call('send');

        $this->assertStringNotContainsString('موقعیت فعلی کاربر', $this->systemPromptOfFirstRequest());
    }

    public function test_foreign_host_and_unknown_urls_are_ignored(): void
    {
        AiProvider::factory()->active()->create();
        $this->actingAsFormEditor();
        $template = FormTemplate::factory()->create(['title' => 'فرم محرمانه']);
        $this->fakeSimpleAnswer();

        Livewire::test(AiAssistantWidget::class)
            ->set('body', 'سلام')
            ->call('send', "https://evil.example/admin/form-templates/{$template->id}/edit");

        $this->assertStringNotContainsString('فرم محرمانه', $this->systemPromptOfFirstRequest());
    }

    public function test_record_the_user_cannot_edit_is_not_revealed_in_context(): void
    {
        AiProvider::factory()->active()->create();
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'فقط دستیار']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $template = FormTemplate::factory()->create(['title' => 'فرم بدون دسترسی']);
        $this->fakeSimpleAnswer();

        Livewire::test(AiAssistantWidget::class)
            ->set('body', 'سلام')
            ->call('send', "/admin/form-templates/{$template->id}/edit");

        $this->assertStringNotContainsString('فرم بدون دسترسی', $this->systemPromptOfFirstRequest());
    }

    public function test_add_field_defaults_to_the_form_on_screen_when_the_model_omits_the_name(): void
    {
        AiProvider::factory()->active()->create();
        $this->actingAsFormEditor();
        $template = FormTemplate::factory()->create(['title' => 'فرم پذیرش']);
        $this->fakeToolCallThenAnswer('add_field_to_form_template', [
            'form_template_name' => '',
            'field' => ['label' => 'شماره موبایل', 'field_key' => 'mobile_number', 'field_type' => FormFieldType::Text->value],
        ]);

        Livewire::test(AiAssistantWidget::class)
            ->set('body', 'یه فیلد شماره موبایل اضافه کن')
            ->call('send', "/admin/form-templates/{$template->id}/edit");

        $this->assertTrue(
            FormField::where('form_template_id', $template->id)->where('field_key', 'mobile_number')->exists()
        );
    }

    public function test_explicit_form_name_wins_over_the_form_on_screen(): void
    {
        AiProvider::factory()->active()->create();
        $this->actingAsFormEditor();
        $onScreen = FormTemplate::factory()->create(['title' => 'فرم پذیرش']);
        $other = FormTemplate::factory()->create(['title' => 'فرم مرخصی']);
        $this->fakeToolCallThenAnswer('add_field_to_form_template', [
            'form_template_name' => 'فرم مرخصی',
            'field' => ['label' => 'علت', 'field_key' => 'reason', 'field_type' => FormFieldType::Text->value],
        ]);

        Livewire::test(AiAssistantWidget::class)
            ->set('body', 'در فرم مرخصی فیلد علت اضافه کن')
            ->call('send', "/admin/form-templates/{$onScreen->id}/edit");

        $this->assertTrue(FormField::where('form_template_id', $other->id)->where('field_key', 'reason')->exists());
        $this->assertFalse(FormField::where('form_template_id', $onScreen->id)->where('field_key', 'reason')->exists());
    }
}
