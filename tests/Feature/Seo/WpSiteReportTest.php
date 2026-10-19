<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\WpConnector\WpCoverageGaps;
use App\Domain\Seo\WpConnector\WpFleetStatus;
use App\Domain\Seo\WpConnector\WpPluginPackage;
use App\Domain\Seo\WpConnector\WpSignature;
use App\Enums\SeoContentItemStatus;
use App\Filament\Pages\SeoContentGaps;
use App\Models\SeoContentItem;
use App\Models\SeoWpSearchTerm;
use App\Models\SeoWpSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WpSiteReportTest extends TestCase
{
    use RefreshDatabase;

    private const SYNC_URL = '/api/v1/wp-connector/sync';

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedSync(SeoWpSite $site, array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp = time();

        return $this->call('POST', self::SYNC_URL, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WP_SITE' => (string) $site->id,
            'HTTP_X_WP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WP_SIGNATURE' => WpSignature::sign($site->secret, $timestamp, $body),
        ], $body);
    }

    private function site(array $scopes): SeoWpSite
    {
        return SeoWpSite::factory()->connected()->create(['scopes' => $scopes, 'plugin_version' => app(WpPluginPackage::class)->version()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function report(): array
    {
        return [
            'wp_core_update' => '6.9.1',
            'outdated_plugins' => [['name' => 'Elementor', 'new_version' => '4.1.0'], ['name' => 'Yoast SEO', 'new_version' => '27.0']],
            'outdated_themes' => 1,
            'ssl' => true,
            'cron_disabled' => false,
            'not_found' => [['path' => '/old-guide', 'hits' => 14], ['path' => '/gone', 'hits' => 3]],
            'search_terms' => [['term' => 'ساعت بازدید', 'count' => 9, 'misses' => 6], ['term' => 'پارکینگ', 'count' => 4, 'misses' => 0]],
        ];
    }

    public function test_a_report_stores_the_health_and_the_404_snapshot_but_not_search_terms_that_were_not_allowed(): void
    {
        $site = $this->site(['content', 'health']);

        $this->signedSync($site, ['report' => $this->report()])->assertOk();

        $site->refresh();
        $this->assertSame('6.9.1', $site->health['wp_core_update']);
        $this->assertSame(['Elementor', 'Yoast SEO'], array_column($site->health['outdated_plugins'], 'name'));
        $this->assertSame(['/old-guide' => 14, '/gone' => 3], $site->notFoundUrls()->pluck('hits', 'path')->all());
        $this->assertSame(0, $site->searchTerms()->count());
    }

    public function test_search_terms_are_stored_only_when_the_owner_allowed_them(): void
    {
        $site = $this->site(['content', 'search_terms']);

        $this->signedSync($site, ['report' => $this->report()])->assertOk();

        $site->refresh();
        $this->assertSame(2, $site->searchTerms()->count());
        $this->assertNull($site->health);
        $this->assertSame(0, $site->notFoundUrls()->count());
    }

    public function test_each_report_replaces_the_previous_snapshot(): void
    {
        $site = $this->site(['content', 'health', 'search_terms']);

        $this->signedSync($site, ['report' => $this->report()])->assertOk();
        $this->signedSync($site, ['report' => ['wp_core_update' => null, 'outdated_plugins' => [], 'not_found' => [['path' => '/new', 'hits' => 1]], 'search_terms' => []]])->assertOk();

        $site->refresh();
        $this->assertNull($site->health['wp_core_update']);
        $this->assertSame([], $site->health['outdated_plugins']);
        $this->assertSame(['/new'], $site->notFoundUrls()->pluck('path')->all());
        $this->assertSame(0, $site->searchTerms()->count());
    }

    public function test_a_sync_without_a_report_keeps_what_was_stored(): void
    {
        $site = $this->site(['content', 'health']);
        $this->signedSync($site, ['report' => $this->report()])->assertOk();

        $this->signedSync($site, ['posts' => []])->assertOk();

        $this->assertSame(2, $site->notFoundUrls()->count());
    }

    public function test_a_malformed_report_is_refused(): void
    {
        $site = $this->site(['content', 'health']);

        $this->signedSync($site, ['report' => ['not_found' => [['hits' => 3]]]])->assertStatus(422);
    }

    public function test_the_fleet_flags_outdated_wordpress_and_lists_the_details(): void
    {
        $site = $this->site(['content', 'health', 'search_terms']);
        $this->signedSync($site, ['report' => $this->report()])->assertOk();

        $row = app(WpFleetStatus::class)->overview()['rows']->first();

        $this->assertSame('updates', $row['state']);
        $this->assertSame('6.9.1', $row['core_update']);
        $this->assertSame('/old-guide', $row['not_found'][0]['path']);
        $this->assertSame([['term' => 'ساعت بازدید', 'misses' => 6]], $row['search_misses']);
    }

    public function test_searches_that_found_nothing_are_listed_as_content_wishes(): void
    {
        $site = $this->site(['content', 'search_terms']);
        $this->signedSync($site, ['report' => $this->report() + []])->assertOk();
        SeoWpSearchTerm::create(['seo_wp_site_id' => $site->id, 'term' => 'یک بار', 'term_hash' => sha1('a'), 'count' => 1, 'misses' => 1]);
        SeoContentItem::factory()->create(['seo_project_id' => $site->seo_project_id, 'target_keyword' => 'پارکینگ']);

        $searches = app(WpCoverageGaps::class)->analyze($site->project)['site_searches'];

        $this->assertSame(['ساعت بازدید'], $searches->pluck('query')->all());
    }

    public function test_a_site_search_becomes_a_calendar_row_with_the_reason(): void
    {
        $site = $this->site(['content', 'search_terms']);
        $this->signedSync($site, ['report' => $this->report()])->assertOk();

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        Livewire::actingAs($admin)
            ->test(SeoContentGaps::class, ['projectId' => $site->seo_project_id])
            ->assertSee('در خود سایت جست‌وجو شد و چیزی پیدا نشد')
            ->call('addToCalendar', 'search', 'ساعت بازدید');

        $item = SeoContentItem::sole();
        $this->assertSame(SeoContentItemStatus::Idea, $item->status);
        $this->assertStringContainsString('9 بار', $item->notes);
        $this->assertStringContainsString('6 بار', $item->notes);
    }
}
