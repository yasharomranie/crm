<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\KeywordIntelligenceService;
use App\Domain\Seo\OpportunityEngine;
use App\Domain\Seo\SeoAlertService;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityType;
use App\Models\SeoAlertSetting;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use App\Models\SeoQueryMetric;
use App\Models\SeoTrackedKeyword;
use App\Models\User;
use App\Notifications\SeoOpportunityDetectedNotification;
use App\Notifications\SeoRankDropNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SeoAlertServiceTest extends TestCase
{
    use RefreshDatabase;

    private function enableAlerts(int $threshold = 5): User
    {
        $user = User::factory()->create();

        SeoAlertSetting::current()->update([
            'is_enabled' => true,
            'rank_drop_threshold' => $threshold,
            'notify_email' => true,
            'recipient_user_ids' => [$user->id],
        ]);

        return $user;
    }

    public function test_it_sends_no_alert_when_the_feature_is_disabled(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        SeoAlertSetting::current()->update(['is_enabled' => false, 'recipient_user_ids' => [$user->id]]);

        $keyword = SeoTrackedKeyword::factory()->create();

        app(SeoAlertService::class)->notifyRankDropIfNeeded($keyword, 3, 15);

        Notification::assertNothingSent();
    }

    public function test_it_alerts_when_a_keyword_drops_out_of_the_top_ten(): void
    {
        Notification::fake();
        $user = $this->enableAlerts();
        $keyword = SeoTrackedKeyword::factory()->create();

        app(SeoAlertService::class)->notifyRankDropIfNeeded($keyword, 3, null);

        Notification::assertSentTo($user, SeoRankDropNotification::class);
    }

    public function test_it_alerts_when_a_drop_meets_the_configured_threshold(): void
    {
        Notification::fake();
        $user = $this->enableAlerts(threshold: 5);
        $keyword = SeoTrackedKeyword::factory()->create();

        app(SeoAlertService::class)->notifyRankDropIfNeeded($keyword, 3, 8);

        Notification::assertSentTo($user, SeoRankDropNotification::class);
    }

    public function test_it_does_not_alert_for_a_small_drop_below_the_threshold(): void
    {
        Notification::fake();
        $this->enableAlerts(threshold: 5);
        $keyword = SeoTrackedKeyword::factory()->create();

        app(SeoAlertService::class)->notifyRankDropIfNeeded($keyword, 3, 5);

        Notification::assertNothingSent();
    }

    public function test_it_does_not_alert_when_there_is_no_previous_position_to_compare(): void
    {
        Notification::fake();
        $this->enableAlerts();
        $keyword = SeoTrackedKeyword::factory()->create();

        app(SeoAlertService::class)->notifyRankDropIfNeeded($keyword, null, 25);

        Notification::assertNothingSent();
    }

    public function test_it_alerts_for_a_new_high_priority_opportunity(): void
    {
        Notification::fake();
        $user = $this->enableAlerts();
        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'priority' => SeoOpportunityPriority::High,
        ]);

        app(SeoAlertService::class)->notifyOpportunityDetected($opportunity);

        Notification::assertSentTo($user, SeoOpportunityDetectedNotification::class);
    }

    public function test_it_does_not_alert_for_a_medium_priority_opportunity(): void
    {
        Notification::fake();
        $this->enableAlerts();
        $project = SeoProject::factory()->create();
        $opportunity = SeoOpportunity::factory()->create([
            'seo_project_id' => $project->id,
            'priority' => SeoOpportunityPriority::Medium,
        ]);

        app(SeoAlertService::class)->notifyOpportunityDetected($opportunity);

        Notification::assertNothingSent();
    }

    public function test_it_sends_no_alert_when_no_recipients_are_configured(): void
    {
        Notification::fake();
        SeoAlertSetting::current()->update(['is_enabled' => true, 'recipient_user_ids' => []]);

        $keyword = SeoTrackedKeyword::factory()->create();

        app(SeoAlertService::class)->notifyRankDropIfNeeded($keyword, 3, null);

        Notification::assertNothingSent();
    }

    /**
     * End-to-end through the real detector, not a hand-built SeoOpportunity
     * - confirms OpportunityEngine actually calls SeoAlertService for a
     * fresh High-priority CTR opportunity (impressions_28d >= 1000 is
     * what makes detectCtrOpportunities() assign High instead of Medium).
     */
    public function test_a_newly_detected_high_priority_opportunity_triggers_an_alert_through_the_real_engine(): void
    {
        Notification::fake();
        $user = $this->enableAlerts();

        $project = SeoProject::factory()->create();
        $query = 'کلمه کلیدی پربازدید';

        for ($i = 0; $i < 28; $i++) {
            SeoQueryMetric::create([
                'seo_project_id' => $project->id,
                'date' => now()->subDays($i)->toDateString(),
                'query' => $query,
                'query_hash' => sha1($query),
                'clicks' => 1,
                'impressions' => 50,
                'ctr' => 0.02,
                'position' => 8.0,
            ]);
        }

        app(KeywordIntelligenceService::class)->rebuildProfiles($project);
        app(OpportunityEngine::class)->scan($project);

        $opportunity = $project->opportunities()->where('type', SeoOpportunityType::CtrOpportunity->value)->firstOrFail();
        $this->assertSame(SeoOpportunityPriority::High, $opportunity->priority);

        Notification::assertSentTo($user, SeoOpportunityDetectedNotification::class);
    }

    public function test_re_scanning_an_already_detected_opportunity_does_not_send_a_second_alert(): void
    {
        Notification::fake();
        $this->enableAlerts();

        $project = SeoProject::factory()->create();
        $query = 'کلمه کلیدی تکراری پربازدید';

        for ($i = 0; $i < 28; $i++) {
            SeoQueryMetric::create([
                'seo_project_id' => $project->id,
                'date' => now()->subDays($i)->toDateString(),
                'query' => $query,
                'query_hash' => sha1($query),
                'clicks' => 1,
                'impressions' => 50,
                'ctr' => 0.02,
                'position' => 8.0,
            ]);
        }

        app(KeywordIntelligenceService::class)->rebuildProfiles($project);
        app(OpportunityEngine::class)->scan($project);
        app(OpportunityEngine::class)->scan($project);

        Notification::assertSentTimes(SeoOpportunityDetectedNotification::class, 1);
    }
}
