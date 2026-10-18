<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\SeoContentItemStatus;
use App\Enums\SeoKeywordTrend;
use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoTechnicalAuditStatus;
use App\Enums\SeoTechnicalIssueSeverity;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\SeoContentItem;
use App\Models\SeoDailyMetric;
use App\Models\SeoGoogleConnection;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordRankCheck;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTechnicalAuditIssue;
use App\Models\SeoTrackedKeyword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the eleven مرکز سئو reporting tools: project overview, traffic
 * trend, top queries/pages, keyword rankings, one keyword's rank history,
 * open opportunities, opportunity outcomes, technical audit summary,
 * content calendar status, and rank movers. Each test creates its own
 * single SeoProject so resolveSeoProject() auto-selects it without a name.
 */
class AiAssistantSeoToolsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFullyPermittedUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل سئو']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report',
        ])->orWhere('name', 'like', '%.view_any')->orWhere('name', 'tasks.create')->get());

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

    public function test_seo_overview_reports_connection_metrics_and_open_items(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create(['name' => 'گنج‌نامه']);
        SeoGoogleConnection::factory()->create(['seo_project_id' => $project->id]);
        SeoDailyMetric::factory()->create([
            'seo_project_id' => $project->id, 'date' => today(),
            'clicks' => 50, 'impressions' => 1000, 'ctr' => 0.05, 'position' => 8.5,
        ]);
        SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'status' => SeoOpportunityStatus::Detected]);
        SeoTechnicalAudit::factory()->create(['seo_project_id' => $project->id, 'score' => 85]);
        SeoContentItem::factory()->create(['seo_project_id' => $project->id, 'status' => SeoContentItemStatus::Idea]);

        $this->fakeToolCallThenAnswer('get_seo_overview', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت سئو چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('گنج‌نامه', $payload['project']);
        $this->assertSame(50, $payload['latest_clicks']);
        $this->assertEqualsWithDelta(5.0, $payload['latest_ctr_percent'], 0.01);
        $this->assertSame(1, $payload['open_opportunities']);
        $this->assertEquals(85, $payload['latest_technical_audit_score']);
        $this->assertSame(1, $payload['content_items_by_status'][SeoContentItemStatus::Idea->getLabel()]);
    }

    public function test_seo_traffic_trend_sums_clicks_and_computes_change(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => today(), 'clicks' => 30]);
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => today()->subDay(), 'clicks' => 20]);
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => today()->subDays(2), 'clicks' => 10]);
        // Previous period: days is clamped to a 7-day minimum, so with
        // days=7 the current window is today-6..today and the previous
        // window is today-13..today-7 - this must land in the latter.
        SeoDailyMetric::factory()->create(['seo_project_id' => $project->id, 'date' => today()->subDays(10), 'clicks' => 15]);

        $this->fakeToolCallThenAnswer('get_seo_traffic_trend', ['days' => 7]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'روند ترافیک این چند روز چطور بوده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(60, $payload['total_clicks']);
        $this->assertCount(3, $payload['daily']);
        $this->assertEqualsWithDelta(300.0, $payload['clicks_change_percent'], 0.01);
    }

    public function test_seo_top_queries_ranks_by_clicks(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        SeoQueryMetric::factory()->create([
            'seo_project_id' => $project->id, 'date' => today(), 'query' => 'ویلا در همدان', 'clicks' => 40, 'impressions' => 800,
        ]);
        SeoQueryMetric::factory()->create([
            'seo_project_id' => $project->id, 'date' => today(), 'query' => 'اجاره ویلا', 'clicks' => 10, 'impressions' => 200,
        ]);

        $this->fakeToolCallThenAnswer('get_seo_top_queries', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدوم عبارت‌های جستجو بیشترین کلیک رو میارن؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('ویلا در همدان', $payload['queries'][0]['query']);
        $this->assertSame(40, $payload['queries'][0]['clicks']);
    }

    public function test_seo_top_pages_ranks_by_clicks(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        SeoPageMetric::factory()->create([
            'seo_project_id' => $project->id, 'date' => today(), 'page_url' => 'https://ganjnameh.ir/villa', 'clicks' => 25,
        ]);
        SeoPageMetric::factory()->create([
            'seo_project_id' => $project->id, 'date' => today(), 'page_url' => 'https://ganjnameh.ir/about', 'clicks' => 5,
        ]);

        $this->fakeToolCallThenAnswer('get_seo_top_pages', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدوم صفحه‌ها بیشترین ترافیک رو میگیرن؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('https://ganjnameh.ir/villa', $payload['pages'][0]['page_url']);
        $this->assertSame(25, $payload['pages'][0]['clicks']);
    }

    public function test_seo_keyword_rankings_filters_by_trend(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'در حال رشد', 'trend' => SeoKeywordTrend::Rising]);
        SeoKeywordProfile::factory()->create(['seo_project_id' => $project->id, 'query' => 'در حال افت', 'trend' => SeoKeywordTrend::Falling]);

        $this->fakeToolCallThenAnswer('get_seo_keyword_rankings', ['trend' => 'rising']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدوم کلیدواژه‌ها در حال رشدن؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['count']);
        $this->assertSame('در حال رشد', $payload['keywords'][0]['query']);
    }

    public function test_seo_keyword_rank_history_lists_position_over_time(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        $tracked = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id, 'keyword' => 'ویلا در همدان']);
        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $tracked->id, 'date' => today()->subDays(2), 'position' => 15]);
        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $tracked->id, 'date' => today(), 'position' => 9]);

        $this->fakeToolCallThenAnswer('get_seo_keyword_rank_history', ['keyword' => 'ویلا']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'رتبه ویلا در همدان چطور بوده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertCount(2, $payload['history']);
        $this->assertSame(9, $payload['history'][1]['position']);
    }

    public function test_seo_open_opportunities_excludes_resolved_ones(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id, 'title' => 'عنوان صفحه بهینه نیست', 'status' => SeoOpportunityStatus::Detected,
        ]);
        SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'status' => SeoOpportunityStatus::Resolved]);

        $this->fakeToolCallThenAnswer('get_seo_open_opportunities', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'چه فرصت‌های سئویی باز داریم؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['open_count']);
        $this->assertSame('عنوان صفحه بهینه نیست', $payload['opportunities'][0]['title']);
    }

    public function test_seo_opportunity_outcomes_summary_counts_by_verdict(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create(['seo_project_id' => $project->id]);
        SeoOpportunityOutcome::factory()->create(['seo_opportunity_id' => $opportunity->id, 'verdict' => SeoOpportunityOutcomeVerdict::Improved]);

        $this->fakeToolCallThenAnswer('get_seo_opportunity_outcomes_summary', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'آیا اقدامات سئو جواب داده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['tracked_count']);
        $this->assertSame(1, $payload['by_verdict'][SeoOpportunityOutcomeVerdict::Improved->getLabel()]);
    }

    public function test_seo_technical_audit_summary_lists_critical_issues(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        $audit = SeoTechnicalAudit::factory()->create([
            'seo_project_id' => $project->id, 'status' => SeoTechnicalAuditStatus::Completed, 'score' => 72,
        ]);
        SeoTechnicalAuditIssue::factory()->create([
            'seo_technical_audit_id' => $audit->id, 'severity' => SeoTechnicalIssueSeverity::Critical, 'url' => 'https://ganjnameh.ir/broken',
        ]);
        SeoTechnicalAuditIssue::factory()->create(['seo_technical_audit_id' => $audit->id, 'severity' => SeoTechnicalIssueSeverity::Warning]);

        $this->fakeToolCallThenAnswer('get_seo_technical_audit_summary', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت فنی سایت چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertEquals(72, $payload['score']);
        $this->assertCount(1, $payload['critical_issues']);
        $this->assertSame('https://ganjnameh.ir/broken', $payload['critical_issues'][0]['url']);
    }

    public function test_seo_content_calendar_status_flags_an_overdue_unpublished_item(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();
        $writer = User::factory()->create(['name' => 'نویسندهٔ محتوا']);
        SeoContentItem::factory()->create([
            'seo_project_id' => $project->id, 'title' => 'مقالهٔ عقب‌افتاده', 'status' => SeoContentItemStatus::Writing,
            'due_date' => today()->subDays(2), 'assigned_to' => $writer->id,
        ]);
        // Published, past due date - must not count as overdue.
        SeoContentItem::factory()->create([
            'seo_project_id' => $project->id, 'status' => SeoContentItemStatus::Published, 'due_date' => today()->subDays(5),
        ]);

        $this->fakeToolCallThenAnswer('get_seo_content_calendar_status', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'تقویم محتوای سئو چه وضعیتی داره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['overdue_count']);
        $this->assertSame('مقالهٔ عقب‌افتاده', $payload['overdue_items'][0]['title']);
        $this->assertSame('نویسندهٔ محتوا', $payload['overdue_items'][0]['assignee']);
    }

    public function test_seo_rank_movers_separates_improved_from_declined(): void
    {
        AiProvider::factory()->active()->create();
        $project = SeoProject::factory()->create();

        $improving = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id, 'keyword' => 'ویلا در همدان']);
        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $improving->id, 'date' => today()->subDays(6), 'position' => 20]);
        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $improving->id, 'date' => today(), 'position' => 12]);

        $declining = SeoTrackedKeyword::factory()->create(['seo_project_id' => $project->id, 'keyword' => 'اجاره ویلا']);
        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $declining->id, 'date' => today()->subDays(6), 'position' => 10]);
        SeoKeywordRankCheck::factory()->create(['seo_tracked_keyword_id' => $declining->id, 'date' => today(), 'position' => 18]);

        $this->fakeToolCallThenAnswer('get_seo_rank_movers', ['days' => 7]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'چه کلیدواژه‌هایی اخیراً رتبه‌شون تغییر کرده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('ویلا در همدان', $payload['improved'][0]['keyword']);
        $this->assertSame(-8, $payload['improved'][0]['change']);
        $this->assertSame('اجاره ویلا', $payload['declined'][0]['keyword']);
        $this->assertSame(8, $payload['declined'][0]['change']);
    }

    public function test_a_user_without_the_report_permission_cannot_use_a_seo_tool(): void
    {
        AiProvider::factory()->active()->create();
        SeoProject::factory()->create();

        $this->fakeToolCallThenAnswer('get_seo_overview', []);

        // A roleless user keeps unrestricted access under this app's
        // Gate::before bypass (AppServiceProvider::registerAccessControl),
        // so the restriction only takes effect once a role is assigned.
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'مشاهده‌گر سئو']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->sole());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت سئو چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertArrayHasKey('error', $payload);
    }
}
