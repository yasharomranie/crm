<?php

namespace Tests\Feature;

use App\Domain\Ai\AiToolAccess;
use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Having "ai-assistant.report" must not be a master key: each tool also
 * needs the permission of the module it reads, search results are limited
 * to modules the user may list, and every tool call leaves an audit trail.
 */
class AiAssistantToolAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsUserWith(array $permissions): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'نقش آزمایشی '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', ...$permissions])->get());

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

    private function toolResultPayload(): array
    {
        return json_decode(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool)->content, true);
    }

    public function test_a_tool_needs_the_permission_of_the_module_it_reads(): void
    {
        $withoutModule = $this->actingAsUserWith([]);
        $this->assertFalse(AiToolAccess::allowsTool($withoutModule, 'get_attendance_summary'));
        $this->assertFalse(AiToolAccess::allowsTool($withoutModule, 'get_wallet_activity_summary'));

        $withModule = $this->actingAsUserWith(['attendance-records.view_any']);
        $this->assertTrue(AiToolAccess::allowsTool($withModule, 'get_attendance_summary'));
        $this->assertFalse(AiToolAccess::allowsTool($withModule, 'get_wallet_activity_summary'));
    }

    public function test_the_model_is_not_offered_tools_for_modules_the_user_cannot_read(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام']]]])]);
        $this->actingAsUserWith(['tasks.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        Http::assertSent(function ($request) {
            $names = collect($request->data()['tools'] ?? [])->pluck('function.name');

            return $names->contains('get_task_summary') && ! $names->contains('get_attendance_summary');
        });
    }

    public function test_a_tool_the_model_calls_anyway_is_refused_without_module_access(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeToolCallThenAnswer('get_attendance_summary', []);
        $this->actingAsUserWith([]);

        Livewire::test(AiAssistant::class)->set('body', 'وضعیت حضور؟')->call('send');

        $this->assertArrayHasKey('error', $this->toolResultPayload());
        $this->assertArrayNotHasKey('on_time_count', $this->toolResultPayload());
    }

    public function test_global_search_leaves_out_modules_the_user_cannot_list(): void
    {
        AiProvider::factory()->active()->create();
        Task::factory()->create(['title' => 'پیگیری قرارداد محرمانه']);
        $this->fakeToolCallThenAnswer('search_everything', ['query' => 'محرمانه']);
        $this->actingAsUserWith([]);

        Livewire::test(AiAssistant::class)->set('body', 'محرمانه رو پیدا کن')->call('send');

        $this->assertSame(0, $this->toolResultPayload()['matched_count']);
    }

    public function test_global_search_includes_modules_the_user_can_list(): void
    {
        AiProvider::factory()->active()->create();
        Task::factory()->create(['title' => 'پیگیری قرارداد محرمانه']);
        $this->fakeToolCallThenAnswer('search_everything', ['query' => 'محرمانه']);
        $this->actingAsUserWith(['tasks.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'محرمانه رو پیدا کن')->call('send');

        $this->assertSame(1, $this->toolResultPayload()['matched_count']);
    }

    public function test_every_tool_call_is_written_to_the_audit_log(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeToolCallThenAnswer('get_task_summary', []);
        $user = $this->actingAsUserWith(['tasks.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'خلاصه وظایف')->call('send');

        $entry = Activity::where('log_name', 'ai_assistant')->sole();
        $this->assertSame($user->id, $entry->causer_id);
        $this->assertSame('get_task_summary', $entry->properties['tool']);
    }
}
