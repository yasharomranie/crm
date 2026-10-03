<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SeoActionQueueService;
use App\Enums\SeoConfidenceLevel;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoTechnicalIssueSeverity;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTechnicalAuditIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoActionQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_merges_opportunities_and_technical_issues_sorted_by_score(): void
    {
        $project = SeoProject::factory()->create();

        SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'title' => 'فرصت کم‌اهمیت',
            'priority' => SeoOpportunityPriority::Low,
            'confidence' => SeoConfidenceLevel::Low,
            'status' => SeoOpportunityStatus::Detected,
        ]);

        SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'title' => 'فرصت پراهمیت',
            'priority' => SeoOpportunityPriority::High,
            // Medium (not High) confidence so this scores below the
            // Critical technical issue (3.0) instead of tying with it -
            // 3.0 * 0.7 = 2.1.
            'confidence' => SeoConfidenceLevel::Medium,
            'status' => SeoOpportunityStatus::Detected,
        ]);

        $audit = SeoTechnicalAudit::factory()->create(['seo_project_id' => $project->id]);
        SeoTechnicalAuditIssue::factory()->create([
            'seo_technical_audit_id' => $audit->id,
            'severity' => SeoTechnicalIssueSeverity::Critical,
        ]);

        $items = app(SeoActionQueueService::class)->items($project);

        $this->assertCount(3, $items);
        $this->assertSame('technical_issue', $items[0]->source);
        $this->assertSame('فرصت پراهمیت', $items[1]->title);
        $this->assertSame('فرصت کم‌اهمیت', $items[2]->title);
        $this->assertTrue($items[0]->score >= $items[1]->score);
        $this->assertTrue($items[1]->score >= $items[2]->score);
    }

    public function test_resolved_and_dismissed_opportunities_are_excluded(): void
    {
        $project = SeoProject::factory()->create();

        SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'status' => SeoOpportunityStatus::Resolved]);
        SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'status' => SeoOpportunityStatus::Dismissed]);
        SeoOpportunity::factory()->create(['seo_project_id' => $project->id, 'status' => SeoOpportunityStatus::Detected]);

        $items = app(SeoActionQueueService::class)->items($project);

        $this->assertCount(1, $items);
    }

    public function test_only_issues_from_the_latest_completed_audit_are_included(): void
    {
        $project = SeoProject::factory()->create();

        $olderAudit = SeoTechnicalAudit::factory()->create(['seo_project_id' => $project->id, 'completed_at' => now()->subWeek()]);
        SeoTechnicalAuditIssue::factory()->create(['seo_technical_audit_id' => $olderAudit->id, 'details' => 'مشکل قدیمی']);

        $newerAudit = SeoTechnicalAudit::factory()->create(['seo_project_id' => $project->id, 'completed_at' => now()]);
        SeoTechnicalAuditIssue::factory()->create(['seo_technical_audit_id' => $newerAudit->id, 'details' => 'مشکل جدید']);

        $items = app(SeoActionQueueService::class)->items($project);

        $this->assertCount(1, $items);
        $this->assertSame('مشکل جدید', $items->first()->detail);
    }

    public function test_a_project_with_nothing_detected_returns_an_empty_queue(): void
    {
        $project = SeoProject::factory()->create();

        $items = app(SeoActionQueueService::class)->items($project);

        $this->assertCount(0, $items);
    }
}
