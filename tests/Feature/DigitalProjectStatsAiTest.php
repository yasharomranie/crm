<?php

namespace Tests\Feature;

use App\Domain\DigitalProjects\DigitalProjectAiSummary;
use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectMetric;
use App\Exceptions\AiProviderException;
use App\Filament\Resources\DigitalProjects\Pages\ListDigitalProjects;
use App\Filament\Resources\DigitalProjects\Pages\ViewDigitalProject;
use App\Filament\Resources\DigitalProjects\RelationManagers\StatsRelationManager;
use App\Models\AiProvider;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use App\Models\DigitalProjectLog;
use App\Models\DigitalProjectStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Digital projects (phase 5): hand-entered channel numbers with their trend,
 * and the on-demand AI summary of the week.
 */
class DigitalProjectStatsAiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function activeProvider(): void
    {
        AiProvider::create([
            'name' => 'Test Provider',
            'base_url' => 'https://api.test-provider.example/v1',
            'api_key' => 'test-key',
            'default_model' => 'gpt-test',
            'is_active' => true,
        ]);
    }

    public function test_a_reading_is_saved_with_who_entered_it(): void
    {
        $admin = $this->admin();
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($admin)
            ->test(StatsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->mountTableAction('create')
            ->setTableActionData(['metric' => DigitalProjectMetric::Followers->value, 'value' => 1250])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $stat = $project->stats()->firstOrFail();

        $this->assertSame(DigitalProjectMetric::Followers, $stat->metric);
        $this->assertSame(1250, $stat->value);
        $this->assertTrue($stat->recorded_on->isToday());
        $this->assertSame($admin->id, $stat->user_id);
    }

    public function test_a_negative_or_missing_number_is_rejected(): void
    {
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(StatsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->mountTableAction('create')
            ->setTableActionData(['metric' => DigitalProjectMetric::Views->value, 'value' => -5])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['value']);

        $this->assertSame(0, DigitalProjectStat::count());
    }

    public function test_the_trend_compares_the_newest_reading_with_the_one_before_it(): void
    {
        $project = DigitalProject::factory()->create();
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 1000, 'recorded_on' => now()->subDays(14)]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 1040, 'recorded_on' => now()->subDays(7)]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'metric' => DigitalProjectMetric::Views, 'value' => 500, 'recorded_on' => today()]);

        $trends = $project->fresh()->statTrends();

        $this->assertSame(1040, $trends['followers']['value']);
        $this->assertSame(40, $trends['followers']['delta']);
        $this->assertNull($trends['views']['delta']);
        $this->assertStringContainsString('+۴۰', $project->fresh()->statTrendsText());
        $this->assertNull(DigitalProject::factory()->create()->statTrendsText());
    }

    public function test_a_drop_shows_a_minus_sign(): void
    {
        $project = DigitalProject::factory()->create();
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 1000, 'recorded_on' => now()->subDays(7)]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 960, 'recorded_on' => today()]);

        $this->assertStringContainsString('−۴۰', $project->fresh()->statTrendsText());
    }

    public function test_the_project_page_and_the_weekly_print_show_the_numbers(): void
    {
        $project = DigitalProject::factory()->create(['name' => 'کانال تلگرام گنجنامه']);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 900, 'recorded_on' => now()->subDays(7)]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 960, 'recorded_on' => today()]);
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ViewDigitalProject::class, ['record' => $project->getRouteKey()])
            ->assertSee('آمار کانال')
            ->assertSee('۹۶۰')
            ->assertSee('+۶۰');

        $this->actingAs($admin)->get(route('digital-projects.weekly-report'))->assertOk()->assertSee('+۶۰');
    }

    public function test_the_ai_prompt_is_short_and_carries_the_weeks_facts(): void
    {
        $project = DigitalProject::factory()->create(['name' => 'پیج اینستاگرام هتل']);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'kind' => DigitalProjectLogKind::Problem, 'body' => 'پست منتشر نشد', 'logged_on' => now()->subDays(2)]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'body' => 'گزارش خیلی قدیمی', 'logged_on' => now()->subDays(30)]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'due_on' => now()->subDay()]);
        DigitalProjectStat::factory()->create(['digital_project_id' => $project->id, 'value' => 900]);

        $prompt = app(DigitalProjectAiSummary::class)->buildPrompt();

        $this->assertStringContainsString('پیج اینستاگرام هتل', $prompt);
        $this->assertStringContainsString('پست منتشر نشد', $prompt);
        $this->assertStringContainsString('از مهلت گذشته: 1', $prompt);
        $this->assertStringContainsString('۹۰۰', $prompt);
        $this->assertStringNotContainsString('گزارش خیلی قدیمی', $prompt);
    }

    public function test_only_the_most_active_projects_are_sent(): void
    {
        DigitalProject::factory()->count(DigitalProjectAiSummary::MAX_PROJECTS + 5)->create();

        $this->assertSame(DigitalProjectAiSummary::MAX_PROJECTS, substr_count(app(DigitalProjectAiSummary::class)->buildPrompt(), 'پروژه «'));
    }

    public function test_it_returns_the_providers_text(): void
    {
        $this->activeProvider();
        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => '  هفتهٔ آرامی بود.  ']]]])]);

        $this->assertSame('هفتهٔ آرامی بود.', app(DigitalProjectAiSummary::class)->summarize());
    }

    public function test_it_fails_clearly_without_an_active_provider(): void
    {
        $this->expectException(AiProviderException::class);

        app(DigitalProjectAiSummary::class)->summarize();
    }

    public function test_the_modal_shows_the_summary_and_caches_it(): void
    {
        Cache::forget('digital-projects-ai-summary');
        $this->activeProvider();
        Http::fake(['api.test-provider.example/*' => Http::response(['choices' => [['message' => ['content' => 'خلاصهٔ این هفته']]]])]);
        DigitalProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->mountAction('aiSummary')
            ->assertSee('خلاصهٔ این هفته');

        $this->assertSame('خلاصهٔ این هفته', Cache::get('digital-projects-ai-summary'));
    }

    public function test_the_modal_explains_a_missing_provider_instead_of_failing(): void
    {
        Cache::forget('digital-projects-ai-summary');

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->mountAction('aiSummary')
            ->assertSee('هیچ ارائه‌دهندهٔ هوش مصنوعی فعالی تنظیم نشده است');

        $this->assertNull(Cache::get('digital-projects-ai-summary'));
    }
}
