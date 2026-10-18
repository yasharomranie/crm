<?php

namespace Tests\Feature;

use App\Domain\Ai\AiCapabilityGuide;
use App\Domain\Ai\AssistantAlertDigestService;
use App\Domain\Ai\AssistantEvalRunner;
use App\Domain\Ai\AssistantUsage;
use App\Domain\Ai\HeadlessAssistant;
use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAssistantUsageDashboard;
use App\Filament\Resources\AiProviders\Pages\ManageAiProviders;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use App\Notifications\AssistantAlertsDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantMeasurementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions = []): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'نقش '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', ...$permissions])->get());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function answer(string $content = 'سلام', array $extra = []): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]], ...$extra];
    }

    private function spend(User $user, int $prompt, int $completion): void
    {
        AiConversation::create(['user_id' => $user->id])->messages()->create([
            'role' => AiMessageRole::Assistant, 'content' => 'x', 'prompt_tokens' => $prompt, 'completion_tokens' => $completion,
        ]);
    }

    public function test_latency_is_stored_with_the_answer(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response($this->answer())]);
        $this->actingAs($this->userWith());

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        $this->assertNotNull(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Assistant)->latency_ms);
    }

    public function test_a_provider_error_is_logged_and_counted_on_the_dashboard(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        $this->assertSame('provider_error', Activity::where('log_name', 'ai_assistant')->sole()->event);
        $this->assertSame(1, Livewire::actingAs($admin)->test(AiAssistantUsageDashboard::class)->instance()->getMainAssistantStats()['errors']);
    }

    public function test_cost_follows_the_configured_token_prices(): void
    {
        config(['ai_assistant.prompt_price_per_million_toman' => 100000, 'ai_assistant.completion_price_per_million_toman' => 400000]);

        $this->assertSame(300000, app(AssistantUsage::class)->costToman(1_000_000, 500_000));
        $this->assertSame(0, app(AssistantUsage::class)->costToman(0, 0));
    }

    public function test_the_cap_percent_warning_and_block_follow_usage(): void
    {
        $usage = app(AssistantUsage::class);
        $user = $this->userWith();

        $this->assertNull($usage->capUsagePercent());
        $this->assertFalse($usage->isOverCap());
        $this->assertNull($usage->capWarning());

        config(['ai_assistant.monthly_token_cap' => 1000]);
        $this->spend($user, 600, 200);

        $this->assertSame(80, $usage->capUsagePercent());
        $this->assertStringContainsString('80٪', (string) $usage->capWarning());
        $this->assertFalse($usage->isOverCap());

        $this->spend($user, 200, 0);

        $this->assertTrue($usage->isOverCap());
        $this->assertStringContainsString('پر شده', (string) $usage->capWarning());
    }

    public function test_a_question_is_refused_once_the_monthly_cap_is_reached(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake();
        config(['ai_assistant.monthly_token_cap' => 100]);
        $user = $this->userWith();
        $this->spend($user, 150, 0);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        Http::assertNothingSent();
        $this->assertSame(1, AiConversation::count());
    }

    public function test_admins_hear_about_the_cap_even_on_a_quiet_day(): void
    {
        Notification::fake();
        config(['ai_assistant.monthly_token_cap' => 1000]);
        $admin = $this->admin();
        $this->spend($admin, 900, 0);

        $this->assertSame(1, app(AssistantAlertDigestService::class)->notifyDailyDigest());

        Notification::assertSentTo($admin, AssistantAlertsDigestNotification::class, fn (AssistantAlertsDigestNotification $notification) => str_contains($notification->toDatabase($admin)['message'], '٪'));
    }

    public function test_the_dashboard_shows_latency_cost_and_cap(): void
    {
        config(['ai_assistant.monthly_token_cap' => 1000, 'ai_assistant.prompt_price_per_million_toman' => 1000000]);
        $admin = $this->admin();
        AiConversation::create(['user_id' => $admin->id])->messages()->create([
            'role' => AiMessageRole::Assistant, 'content' => 'x', 'prompt_tokens' => 500_000, 'completion_tokens' => 0, 'latency_ms' => 1500,
        ]);

        $stats = Livewire::actingAs($admin)->test(AiAssistantUsageDashboard::class)->instance()->getMainAssistantStats();

        $this->assertSame(1500, $stats['avg_latency_ms']);
        $this->assertSame(500000, $stats['cost_toman']);
        $this->assertSame(50000, $stats['cap_percent']);
    }

    public function test_a_chosen_model_is_what_the_connection_test_uses(): void
    {
        $provider = AiProvider::factory()->active()->create(['default_model' => 'gpt-4o-mini']);
        Http::fake(['*' => Http::response($this->answer('آماده'))]);

        Livewire::actingAs($this->admin())->test(ManageAiProviders::class)
            ->callTableAction('testConnection', $provider, data: ['model' => 'gpt-test-x'])
            ->assertNotified('اتصال برقرار است');

        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-test-x');
    }

    public function test_the_guide_lists_only_the_parts_the_user_may_see(): void
    {
        $labels = array_column(AiCapabilityGuide::forUser($this->userWith(['attendance-records.view_any', 'tasks.view_any'])), 'label');

        $this->assertEqualsCanonicalizing(['وظایف', 'حضور و غیاب'], $labels);
        $this->assertSame([], AiCapabilityGuide::forUser(null));
    }

    public function test_the_page_offers_the_guide_to_someone_with_access(): void
    {
        $this->actingAs($this->userWith(['tasks.view_any']));

        Livewire::test(AiAssistant::class)
            ->assertSee('چه چیزهایی می‌توانم بپرسم؟')
            ->assertSee('کدام وظایف عقب افتاده‌اند و مسئولشان کیست؟');
    }

    public function test_every_tool_the_evaluation_set_expects_really_exists(): void
    {
        $available = app(HeadlessAssistant::class)->toolNamesFor($this->admin());

        foreach (AssistantEvalRunner::questions() as $case) {
            $this->assertNotSame('', trim($case['question']));

            foreach ($case['expect'] as $tool) {
                $this->assertContains($tool, $available, "«{$case['question']}» expects the unknown tool {$tool}");
            }
        }

        $this->assertGreaterThanOrEqual(50, count(AssistantEvalRunner::questions()));
    }

    private ?string $forcedTool = null;

    /**
     * The fake model picks the first expected tool of each question, or
     * $this->forcedTool when that is set (read at call time, so a test can
     * change it between runs).
     */
    private function fakeModelThatPicks(): void
    {
        $expectations = collect(AssistantEvalRunner::questions())->mapWithKeys(fn (array $case) => [$case['question'] => $case['expect']]);

        Http::fake(function (Request $request) use ($expectations) {
            $question = collect($request['messages'])->last(fn (array $message) => $message['role'] === 'user')['content'];
            $tool = $this->forcedTool ?? ($expectations[$question][0] ?? null);

            return Http::response($tool === null
                ? $this->answer('خواهش می‌کنم')
                : ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c', 'type' => 'function', 'function' => ['name' => $tool, 'arguments' => '{}']]]]]]]);
        });
    }

    public function test_a_model_that_picks_the_right_tools_passes_the_evaluation(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeModelThatPicks();

        $report = app(AssistantEvalRunner::class)->run($this->admin());

        $this->assertSame($report['total'], $report['passed']);
        $this->assertSame(100, $report['percent']);
        $this->assertSame([], $report['failures']);
    }

    public function test_wrong_picks_are_reported_with_whether_the_right_tool_was_offered(): void
    {
        AiProvider::factory()->active()->create();
        $this->forcedTool = 'get_seo_overview';
        $this->fakeModelThatPicks();

        $report = app(AssistantEvalRunner::class)->run($this->admin(), 3);

        $this->assertSame(3, $report['total']);
        $this->assertLessThan(100, $report['percent']);
        $this->assertSame(['get_seo_overview'], $report['failures'][0]['chosen']);
        $this->assertTrue($report['failures'][0]['expected_was_offered']);
    }

    public function test_the_command_fails_below_the_minimum_and_passes_above_it(): void
    {
        AiProvider::factory()->active()->create();
        $this->admin();

        $this->fakeModelThatPicks();

        $this->forcedTool = 'get_seo_overview';
        $this->assertSame(1, Artisan::call('ai-assistant:eval', ['--limit' => 3, '--min' => 90]));
        $this->assertStringContainsString('expected:', Artisan::output());

        $this->forcedTool = null;
        $this->assertSame(0, Artisan::call('ai-assistant:eval', ['--limit' => 3, '--min' => 90]));
    }

    public function test_the_command_explains_when_there_is_no_provider_or_admin(): void
    {
        $this->assertSame(1, Artisan::call('ai-assistant:eval'));
        $this->assertStringContainsString('No user', Artisan::output());

        $this->admin();
        $this->assertSame(1, Artisan::call('ai-assistant:eval'));
        $this->assertStringContainsString('No active AI provider', Artisan::output());
    }
}
