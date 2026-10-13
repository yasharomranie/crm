<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\OpportunityEvidenceFormatter;
use App\Enums\SeoConfidenceLevel;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityEvidenceFormatterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_translates_known_action_codes(): void
    {
        $this->assertSame('بررسی بک‌لینک‌ها', OpportunityEvidenceFormatter::actionLabel('check_backlinks'));
        $this->assertSame('بازبینی عنوان صفحه', OpportunityEvidenceFormatter::actionLabel('review_title'));
    }

    public function test_it_translates_known_evidence_keys(): void
    {
        $this->assertSame('ایمپرشن (۲۸ روز اخیر)', OpportunityEvidenceFormatter::evidenceKeyLabel('impressions_28d'));
        $this->assertSame('CTR مرجع برای این رتبه', OpportunityEvidenceFormatter::evidenceKeyLabel('benchmark_ctr_for_position'));
    }

    public function test_an_unmapped_action_code_falls_back_to_the_raw_string(): void
    {
        $this->assertSame('some_future_action', OpportunityEvidenceFormatter::actionLabel('some_future_action'));
    }

    public function test_an_unmapped_evidence_key_falls_back_to_the_raw_string(): void
    {
        $this->assertSame('some_future_key', OpportunityEvidenceFormatter::evidenceKeyLabel('some_future_key'));
    }

    public function test_the_opportunity_evidence_view_renders_translated_labels_instead_of_raw_codes(): void
    {
        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::create([
            'seo_project_id' => $project->id,
            'type' => 'ctr_opportunity',
            'subject_query' => 'اجاره ویلا همدان',
            'title' => 'تست',
            'evidence' => ['impressions_28d' => 500, 'benchmark_ctr_for_position' => 0.08],
            'recommended_actions' => ['review_title', 'check_backlinks'],
            'priority' => SeoOpportunityPriority::Medium,
            'confidence' => SeoConfidenceLevel::Medium,
            'status' => SeoOpportunityStatus::Detected,
            'detected_at' => now(),
        ]);

        $html = view('filament.seo.opportunity-evidence', ['opportunity' => $opportunity])->render();

        $this->assertStringContainsString('ایمپرشن (۲۸ روز اخیر)', $html);
        $this->assertStringContainsString('CTR مرجع برای این رتبه', $html);
        $this->assertStringContainsString('بازبینی عنوان صفحه', $html);
        $this->assertStringContainsString('بررسی بک‌لینک‌ها', $html);
        $this->assertStringNotContainsString('impressions_28d', $html);
        $this->assertStringNotContainsString('check_backlinks', $html);
    }
}
