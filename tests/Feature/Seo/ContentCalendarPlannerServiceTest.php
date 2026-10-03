<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\ContentCalendarPlannerService;
use App\Enums\SeoContentItemStatus;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContentCalendarPlannerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_proposes_dates_for_unscheduled_content_items(): void
    {
        $project = SeoProject::factory()->create();
        SeoContentItem::factory()->create(['seo_project_id' => $project->id, 'status' => SeoContentItemStatus::Idea, 'due_date' => null, 'title' => 'ایده‌ی اول']);
        SeoContentItem::factory()->create(['seo_project_id' => $project->id, 'status' => SeoContentItemStatus::Planned, 'due_date' => null, 'title' => 'ایده‌ی دوم']);

        $proposal = app(ContentCalendarPlannerService::class)->proposeSchedule(
            $project,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            desiredVolume: 2,
        );

        $this->assertCount(2, $proposal);
        $this->assertTrue($proposal->every(fn (array $row) => $row['content_item_id'] !== null));
    }

    public function test_it_includes_approved_suggestions_without_a_content_item_yet(): void
    {
        $project = SeoProject::factory()->create();
        $suggestion = SeoKeywordSuggestion::factory()->create([
            'seo_project_id' => $project->id,
            'status' => SeoKeywordSuggestionStatus::Approved,
            'keyword' => 'اجاره ویلا همدان',
        ]);

        $proposal = app(ContentCalendarPlannerService::class)->proposeSchedule(
            $project,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            desiredVolume: 5,
        );

        $this->assertCount(1, $proposal);
        $this->assertSame($suggestion->id, $proposal->first()['keyword_suggestion_id']);
        $this->assertSame('اجاره ویلا همدان', $proposal->first()['title']);
    }

    public function test_it_excludes_a_suggestion_that_already_has_a_content_item(): void
    {
        $project = SeoProject::factory()->create();
        $suggestion = SeoKeywordSuggestion::factory()->create([
            'seo_project_id' => $project->id,
            'status' => SeoKeywordSuggestionStatus::Approved,
        ]);
        SeoContentItem::factory()->create([
            'seo_project_id' => $project->id,
            'keyword_suggestion_id' => $suggestion->id,
            'due_date' => '2026-09-01',
        ]);

        $proposal = app(ContentCalendarPlannerService::class)->proposeSchedule(
            $project,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
            desiredVolume: 5,
        );

        $this->assertCount(0, $proposal);
    }

    public function test_it_defaults_the_volume_to_the_projects_recent_publishing_pace(): void
    {
        $project = SeoProject::factory()->create();

        // ۶ مورد منتشرشده در ۳ ماه اخیر = ۲ مورد در ماه.
        for ($i = 0; $i < 6; $i++) {
            SeoContentItem::factory()->create([
                'seo_project_id' => $project->id,
                'status' => SeoContentItemStatus::Published,
                'published_at' => now()->subWeeks($i),
            ]);
        }

        for ($i = 0; $i < 5; $i++) {
            SeoContentItem::factory()->create([
                'seo_project_id' => $project->id,
                'status' => SeoContentItemStatus::Idea,
                'due_date' => null,
                'title' => "ایده {$i}",
            ]);
        }

        $proposal = app(ContentCalendarPlannerService::class)->proposeSchedule(
            $project,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertCount(2, $proposal);
    }

    public function test_it_defaults_the_volume_to_four_when_there_is_no_publishing_history(): void
    {
        $project = SeoProject::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            SeoContentItem::factory()->create([
                'seo_project_id' => $project->id,
                'status' => SeoContentItemStatus::Idea,
                'due_date' => null,
                'title' => "ایده {$i}",
            ]);
        }

        $proposal = app(ContentCalendarPlannerService::class)->proposeSchedule(
            $project,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertCount(4, $proposal);
    }

    public function test_it_returns_an_empty_proposal_when_there_is_no_backlog(): void
    {
        $project = SeoProject::factory()->create();

        $proposal = app(ContentCalendarPlannerService::class)->proposeSchedule(
            $project,
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-31'),
        );

        $this->assertCount(0, $proposal);
    }

    public function test_applying_the_schedule_updates_existing_items_and_creates_new_ones_from_suggestions(): void
    {
        $project = SeoProject::factory()->create();
        $existing = SeoContentItem::factory()->create(['seo_project_id' => $project->id, 'status' => SeoContentItemStatus::Idea, 'due_date' => null]);
        SeoKeywordSuggestion::factory()->create([
            'seo_project_id' => $project->id,
            'status' => SeoKeywordSuggestionStatus::Approved,
            'keyword' => 'تور غار علیصدر',
            'suggested_content_type' => 'صفحه فرود',
        ]);

        $planner = app(ContentCalendarPlannerService::class);
        $proposal = $planner->proposeSchedule($project, Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), desiredVolume: 5);

        $applied = $planner->applySchedule($project, $proposal);

        $this->assertSame(2, $applied);
        $this->assertSame(SeoContentItemStatus::Planned, $existing->refresh()->status);
        $this->assertNotNull($existing->due_date);

        $created = SeoContentItem::where('target_keyword', 'تور غار علیصدر')->first();
        $this->assertNotNull($created);
        $this->assertSame('صفحه فرود', $created->content_type);
        $this->assertSame(SeoContentItemStatus::Planned, $created->status);
        $this->assertNotNull($created->due_date);
    }
}
