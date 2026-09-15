<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\DealStage;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Deal;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * search_everything: the one AI-assistant tool that isn't scoped to a
 * single module. Before this, a loosely-worded question like "قالب سایت
 * رابیان" (a client name + a keyword, with no hint of which module it
 * lives in) had no way to be answered - every other tool needs the user
 * or the model to already know which module/table to look in. This
 * searches tasks, projects, deals, and every answered value inside every
 * submitted form all at once, ranked by how many query words each match
 * carries.
 */
class AiAssistantGlobalSearchToolTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFullyPermittedUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل جست‌وجوی سراسری']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report'])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fakeToolCallThenAnswer(string $toolName, array $arguments, string $finalAnswer = 'باشه.'): void
    {
        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => $finalAnswer],
                ]],
            ]);
    }

    private function toolResultPayload(): array
    {
        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);

        return json_decode($toolMessage->content, true);
    }

    public function test_it_finds_matches_across_multiple_modules_ranked_by_word_overlap(): void
    {
        AiProvider::factory()->active()->create();

        // Both words match: should rank first.
        $bestMatch = Task::factory()->create(['title' => 'قالب سایت رابیان', 'description' => null]);
        // Only one word matches: should still be found, but ranked lower.
        $partialMatch = Project::create(['code' => 'PRJ-1', 'title' => 'طراحی سایت شرکت دیگر']);
        // No overlap at all: must not appear.
        Deal::create(['title' => 'خرید تجهیزات اداری', 'stage' => DealStage::NewLead, 'value' => 1_000_000]);

        $this->fakeToolCallThenAnswer('search_everything', ['query' => 'قالب سایت رابیان']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'قالب سایت رابیان رو پیدا کن')
            ->call('send');

        $payload = $this->toolResultPayload();
        $titles = collect($payload['results'])->pluck('title');

        $this->assertSame('وظیفه', $payload['results'][0]['type']);
        $this->assertSame($bestMatch->title, $payload['results'][0]['title']);
        $this->assertTrue($titles->contains($partialMatch->title));
        $this->assertFalse($titles->contains('خرید تجهیزات اداری'));
    }

    /**
     * The specific gap the user reported: a value typed INTO a form field
     * (not the form's own title, and not any field's label) has to be
     * searchable too - a plain "search form titles" tool would miss this
     * entirely.
     */
    public function test_it_finds_a_value_typed_inside_a_form_submission_not_just_form_or_field_names(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'صورتجلسه کمیسیون هفتگی']);
        $field = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'project_name',
            'label' => 'نام پروژه',
        ]);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $submission->values()->create(['form_field_id' => $field->id, 'value_text' => 'قالب سایت رابیان تحویل داده شد']);

        $this->fakeToolCallThenAnswer('search_everything', ['query' => 'قالب سایت رابیان']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'قالب سایت رابیان رو پیدا کن')
            ->call('send');

        $payload = $this->toolResultPayload();
        $formResult = collect($payload['results'])->firstWhere('type', 'پاسخ ثبت‌شده در فرم');

        $this->assertNotNull($formResult);
        $this->assertSame($template->title, $formResult['form']);
        $this->assertSame($submission->id, $formResult['submission_id']);
        $this->assertStringContainsString('قالب سایت رابیان', $formResult['snippet']);
    }

    public function test_an_empty_query_returns_an_error_instead_of_the_whole_database(): void
    {
        AiProvider::factory()->active()->create();

        $this->fakeToolCallThenAnswer('search_everything', ['query' => '   ']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه چیزی پیدا کن')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertArrayHasKey('error', $payload);
    }

    public function test_it_is_not_offered_without_the_report_permission(): void
    {
        AiProvider::factory()->active()->create();
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'بدون دسترسی گزارش‌گیری']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->first());
        $user = User::factory()->create();
        $user->assignRole($role);

        Http::fakeSequence()->push(['choices' => [[
            'message' => ['role' => 'assistant', 'content' => 'اجازهٔ دسترسی به این ابزار را ندارید.'],
        ]]]);

        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'قالب سایت رابیان رو پیدا کن')
            ->call('send');

        $sentTools = collect(json_decode(Http::recorded()[0][0]->body(), true)['tools'] ?? [])
            ->pluck('function.name');

        $this->assertFalse($sentTools->contains('search_everything'));
    }
}
