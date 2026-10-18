<?php

namespace Tests\Feature;

use App\Domain\Ai\AiToolAccess;
use App\Domain\Ai\AiToolRouter;
use App\Enums\AiMessageRole;
use App\Exceptions\AiProviderException;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantCostAndControlTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsUserWith(array $permissions): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'نقش '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', ...$permissions])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function tool(string $name): array
    {
        return ['type' => 'function', 'function' => ['name' => $name]];
    }

    /**
     * @return list<string>
     */
    private function names(array $tools): array
    {
        return array_column(array_column($tools, 'function'), 'name');
    }

    private function allTools(): array
    {
        return array_map($this->tool(...), [
            'search_everything', 'list_alerts', 'create_task', 'add_field_to_form_template',
            'get_attendance_summary', 'get_not_checked_in_today', 'get_crm_summary', 'get_seo_overview', 'get_task_summary',
        ]);
    }

    public function test_a_question_about_one_module_only_sends_that_modules_tools(): void
    {
        $selected = $this->names(app(AiToolRouter::class)->select($this->allTools(), 'امروز چه کسانی هنوز حضور نزده‌اند؟'));

        $this->assertContains('get_attendance_summary', $selected);
        $this->assertContains('get_not_checked_in_today', $selected);
        $this->assertContains('search_everything', $selected);
        $this->assertContains('add_field_to_form_template', $selected);
        $this->assertNotContains('get_seo_overview', $selected);
        $this->assertNotContains('get_crm_summary', $selected);
    }

    public function test_a_vague_question_sends_every_tool(): void
    {
        $all = $this->allTools();

        $this->assertCount(count($all), app(AiToolRouter::class)->select($all, 'وضعیت کلی امروز رو خلاصه کن'));
    }

    public function test_tools_used_earlier_in_the_conversation_stay_available(): void
    {
        $selected = $this->names(app(AiToolRouter::class)->select($this->allTools(), 'وضعیت سئو چطوره؟', ['get_crm_summary']));

        $this->assertContains('get_seo_overview', $selected);
        $this->assertContains('get_crm_summary', $selected);
        $this->assertNotContains('get_attendance_summary', $selected);
    }

    public function test_routing_can_be_switched_off(): void
    {
        config(['ai_assistant.tool_routing' => false]);
        $all = $this->allTools();

        $this->assertCount(count($all), app(AiToolRouter::class)->select($all, 'وضعیت سئو چطوره؟'));
    }

    public function test_progress_labels_name_the_module(): void
    {
        $this->assertSame('در حال گرفتن اطلاعات از «حضور و غیاب»…', AiToolAccess::progressLabel('get_attendance_summary'));
        $this->assertSame('در حال بررسی…', AiToolAccess::progressLabel('search_everything'));
    }

    public function test_a_failing_provider_falls_back_to_the_standby_one(): void
    {
        Sleep::fake();
        AiProvider::factory()->create(['name' => 'اصلی', 'base_url' => 'https://primary.test/v1', 'is_active' => true]);
        AiProvider::factory()->create(['name' => 'پشتیبان', 'base_url' => 'https://standby.test/v1', 'default_model' => 'standby-model', 'is_fallback' => true]);
        Http::fake([
            'primary.test/*' => Http::response(['error' => ['message' => 'down']], 503),
            'standby.test/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'از پشتیبان']]]]),
        ]);
        $this->actingAsUserWith([]);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        $this->assertSame('از پشتیبان', AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Assistant)->content);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'standby.test') && $request['model'] === 'standby-model');
    }

    public function test_the_error_stands_when_there_is_no_standby_provider(): void
    {
        Sleep::fake();
        $primary = AiProvider::factory()->create(['base_url' => 'https://primary.test/v1', 'is_active' => true]);
        Http::fake(['*' => Http::response(['error' => ['message' => 'down']], 503)]);

        $this->expectException(AiProviderException::class);

        $primary->chatCompletionWithFallback([['role' => 'user', 'content' => 'hi']]);
    }

    public function test_only_one_provider_is_the_standby_and_the_active_one_never_is(): void
    {
        $first = AiProvider::factory()->create(['is_fallback' => true]);
        $second = AiProvider::factory()->create(['is_fallback' => true]);

        $this->assertFalse($first->fresh()->is_fallback);
        $this->assertTrue($second->fresh()->is_fallback);

        $second->update(['is_active' => true]);

        $this->assertFalse($second->fresh()->is_fallback);
        $this->assertNull(AiProvider::fallback());
    }

    public function test_the_status_endpoint_reports_the_running_question_of_the_user_only(): void
    {
        $user = $this->actingAsUserWith([]);
        AiConversation::create(['user_id' => User::factory()->create()->id, 'run_label' => 'مال دیگری']);

        $this->getJson(route('ai-assistant.run-status'))->assertOk()->assertJson(['running' => false]);

        AiConversation::create(['user_id' => $user->id, 'run_label' => 'در حال گرفتن اطلاعات از «وظایف»…']);

        $this->getJson(route('ai-assistant.run-status'))->assertOk()->assertJson([
            'running' => true,
            'label' => 'در حال گرفتن اطلاعات از «وظایف»…',
            'stopping' => false,
        ]);
    }

    public function test_the_stop_endpoint_flags_only_my_running_question(): void
    {
        $user = $this->actingAsUserWith([]);
        $mine = AiConversation::create(['user_id' => $user->id, 'run_label' => 'در حال بررسی…']);
        $theirs = AiConversation::create(['user_id' => User::factory()->create()->id, 'run_label' => 'در حال بررسی…']);

        $this->postJson(route('ai-assistant.stop'))->assertOk()->assertJson(['stopping' => true]);

        $this->assertTrue($mine->fresh()->cancel_requested);
        $this->assertFalse($theirs->fresh()->cancel_requested);
    }

    public function test_the_run_endpoints_need_access_to_the_assistant(): void
    {
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'بدون دستیار']);
        $role->givePermissionTo(Permission::where('name', 'tasks.view_any')->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->getJson(route('ai-assistant.run-status'))->assertForbidden();
        $this->postJson(route('ai-assistant.stop'))->assertForbidden();
    }

    public function test_a_stopped_question_ends_between_rounds_without_calling_the_model_again(): void
    {
        AiProvider::factory()->active()->create();
        $user = $this->actingAsUserWith(['tasks.view_any']);
        $calls = 0;

        Http::fake(function (Request $request) use (&$calls) {
            $calls++;

            // The manager presses "stop" while the first model call is in flight.
            AiConversation::query()->update(['cancel_requested' => true]);

            return Http::response(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'get_task_summary', 'arguments' => '{}']]],
            ]]]]);
        });

        Livewire::test(AiAssistant::class)->set('body', 'خلاصه وظایف')->call('send');

        $conversation = AiConversation::sole();
        $this->assertSame(1, $calls);
        $this->assertSame('پاسخ‌دهی به درخواست شما متوقف شد.', $conversation->messages->last()->content);
        $this->assertNull($conversation->run_label);
        $this->assertFalse($conversation->cancel_requested);
        $this->assertSame($user->id, $conversation->user_id);
    }

    public function test_earlier_questions_are_kept_as_a_short_digest_when_old_turns_are_dropped(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'باشه']]]])]);
        $user = $this->actingAsUserWith([]);
        $conversation = AiConversation::create(['user_id' => $user->id]);

        foreach (range(1, 14) as $turn) {
            $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => "سؤال شمارهٔ {$turn}"]);
            $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => "پاسخ {$turn}"]);
        }

        Livewire::test(AiAssistant::class)->set('conversationId', $conversation->id)->set('body', 'سؤال جدید')->call('send');

        Http::assertSent(function (Request $request) {
            $digest = collect($request->data()['messages'])->first(
                fn (array $message) => $message['role'] === 'system' && str_contains($message['content'], 'پرسش‌های قبلی همین گفتگو'),
            );

            return $digest !== null
                && str_contains($digest['content'], 'سؤال شمارهٔ 1')
                && ! str_contains($digest['content'], 'سؤال شمارهٔ 14');
        });
    }
}
