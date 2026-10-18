<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\WpConnector\WpPairingService;
use App\Domain\Seo\WpConnector\WpSignature;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WpConnectorTest extends TestCase
{
    use RefreshDatabase;

    private const PAIR_URL = '/api/v1/wp-connector/pair';

    private const SYNC_URL = '/api/v1/wp-connector/sync';

    /**
     * @return array<string, mixed>
     */
    private function samplePost(array $overrides = []): array
    {
        return $overrides + [
            'wp_id' => 11,
            'post_type' => 'post',
            'status' => 'publish',
            'url' => 'https://example.test/hamedan-guide',
            'title' => 'راهنمای سفر به همدان',
            'slug' => 'hamedan-guide',
            'published_at' => '2026-10-01T08:00:00Z',
            'modified_at' => '2026-10-10T08:00:00Z',
            'word_count' => 850,
            'author_name' => 'سارا',
            'headings' => [['level' => 1, 'text' => 'همدان'], ['level' => 2, 'text' => 'جاذبه‌ها']],
            'internal_links' => [['url' => 'https://example.test/ganjnameh', 'anchor' => 'گنجنامه']],
            'external_links' => [['url' => 'https://other.test', 'anchor' => 'منبع', 'nofollow' => true]],
            'images' => ['count' => 4, 'missing_alt' => 1],
            'terms' => ['categories' => ['سفر'], 'tags' => ['همدان']],
            'seo' => ['focus_keyword' => 'سفر به همدان', 'title' => 'سفر به همدان', 'description' => 'توضیح', 'noindex' => false, 'secret_field' => 'x'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signedPost(SeoWpSite $site, string $url, array $payload, ?int $timestamp = null, ?string $secret = null): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $timestamp ??= time();

        return $this->call('POST', $url, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WP_SITE' => (string) $site->id,
            'HTTP_X_WP_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_WP_SIGNATURE' => WpSignature::sign($secret ?? $site->secret, $timestamp, $body),
        ], $body);
    }

    private function connectedSite(array $scopes = ['content', 'seo_meta', 'structure', 'images', 'terms', 'authors']): SeoWpSite
    {
        return SeoWpSite::factory()->connected()->create(['scopes' => $scopes]);
    }

    public function test_a_pairing_code_is_traded_for_a_secret_once(): void
    {
        $project = SeoProject::factory()->create();
        $code = app(WpPairingService::class)->issueCode($project);

        $payload = ['code' => $code, 'site_url' => 'https://example.test', 'plugin_version' => '1.0.0', 'seo_plugin' => 'yoast', 'scopes' => ['content', 'seo_meta']];

        $response = $this->postJson(self::PAIR_URL, $payload)->assertOk();

        $site = $project->wpSite->refresh();
        $this->assertSame($site->id, $response->json('site_id'));
        $this->assertSame($site->secret, $response->json('secret'));
        $this->assertTrue($site->isConnected());
        $this->assertSame('yoast', $site->seo_plugin);
        $this->assertSame(['content', 'seo_meta'], $site->scopes);

        $this->postJson(self::PAIR_URL, $payload)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_CODE');
    }

    public function test_an_expired_or_wrong_code_is_refused(): void
    {
        $project = SeoProject::factory()->create();
        $code = app(WpPairingService::class)->issueCode($project);
        $project->wpSite->update(['pairing_expires_at' => now()->subMinute()]);

        $this->postJson(self::PAIR_URL, ['code' => $code, 'site_url' => 'https://example.test'])->assertStatus(422);
        $this->postJson(self::PAIR_URL, ['code' => 'WRONG-CODE-0000', 'site_url' => 'https://example.test'])->assertStatus(422);
        $this->assertFalse($project->wpSite->refresh()->isConnected());
    }

    public function test_a_signed_sync_stores_the_posts(): void
    {
        $site = $this->connectedSite();

        $this->signedPost($site, self::SYNC_URL, ['site' => ['plugin_version' => '1.1.0'], 'posts' => [$this->samplePost()], 'cursor' => 'c1'])
            ->assertOk()
            ->assertJsonPath('accepted', 1)
            ->assertJsonPath('cursor', 'c1');

        $stored = SeoWpPost::sole();
        $this->assertSame('راهنمای سفر به همدان', $stored->title);
        $this->assertSame('سارا', $stored->author_name);
        $this->assertSame(['سفر'], $stored->terms['categories']);
        $this->assertSame(1, $stored->images['missing_alt']);
        $this->assertSame('سفر به همدان', $stored->seo['focus_keyword']);
        $this->assertArrayNotHasKey('secret_field', $stored->seo);
        $this->assertSame(SeoWpPost::hashUrl('https://example.test/hamedan-guide'), $stored->url_hash);
        $this->assertSame('1.1.0', $site->refresh()->plugin_version);
        $this->assertNotNull($site->last_sync_at);
    }

    public function test_syncing_again_updates_instead_of_duplicating(): void
    {
        $site = $this->connectedSite();

        $this->signedPost($site, self::SYNC_URL, ['posts' => [$this->samplePost()]])->assertOk();
        $this->signedPost($site, self::SYNC_URL, ['posts' => [$this->samplePost(['title' => 'عنوان تازه'])]])->assertOk();

        $this->assertSame(1, SeoWpPost::count());
        $this->assertSame('عنوان تازه', SeoWpPost::sole()->title);
    }

    public function test_fields_outside_the_granted_scopes_are_dropped(): void
    {
        $site = $this->connectedSite(['content']);

        $this->signedPost($site, self::SYNC_URL, ['posts' => [$this->samplePost()]])->assertOk();

        $stored = SeoWpPost::sole();
        $this->assertNull($stored->author_name);
        $this->assertNull($stored->seo);
        $this->assertNull($stored->headings);
        $this->assertNull($stored->internal_links);
        $this->assertNull($stored->images);
        $this->assertNull($stored->terms);
        $this->assertSame(850, $stored->word_count);
    }

    public function test_a_sync_without_the_content_scope_is_refused(): void
    {
        $site = $this->connectedSite(['seo_meta']);

        $this->signedPost($site, self::SYNC_URL, ['posts' => [$this->samplePost()]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SCOPE_REQUIRED');
        $this->assertSame(0, SeoWpPost::count());
    }

    public function test_deleted_posts_are_removed_and_other_sites_are_untouched(): void
    {
        $site = $this->connectedSite();
        $other = $this->connectedSite();
        $this->signedPost($site, self::SYNC_URL, ['posts' => [$this->samplePost()]])->assertOk();
        $this->signedPost($other, self::SYNC_URL, ['posts' => [$this->samplePost()]])->assertOk();

        $this->signedPost($site, self::SYNC_URL, ['deleted' => [11]])->assertOk()->assertJsonPath('deleted', 1);

        $this->assertSame(0, $site->posts()->count());
        $this->assertSame(1, $other->posts()->count());
    }

    public function test_a_bad_signature_a_stale_timestamp_and_a_disconnected_site_are_refused(): void
    {
        $site = $this->connectedSite();

        $this->signedPost($site, self::SYNC_URL, ['posts' => []], secret: 'not-the-secret')
            ->assertStatus(401)->assertJsonPath('error.code', 'BAD_SIGNATURE');

        $this->signedPost($site, self::SYNC_URL, ['posts' => []], timestamp: time() - 3600)
            ->assertStatus(401)->assertJsonPath('error.code', 'STALE_TIMESTAMP');

        $this->postJson(self::SYNC_URL, ['posts' => []])->assertStatus(401);

        $secret = $site->secret;
        app(WpPairingService::class)->disconnect($site);
        $this->signedPost($site, self::SYNC_URL, ['posts' => []], secret: $secret)->assertStatus(401);
    }

    public function test_more_than_a_hundred_posts_in_one_batch_is_refused(): void
    {
        $site = $this->connectedSite();
        $posts = array_map(fn (int $id) => $this->samplePost(['wp_id' => $id, 'url' => "https://example.test/p-{$id}"]), range(1, 101));

        $this->signedPost($site, self::SYNC_URL, ['posts' => $posts])->assertStatus(422);
    }

    public function test_the_plugin_can_disconnect_itself(): void
    {
        $site = $this->connectedSite();

        $this->signedPost($site, '/api/v1/wp-connector/disconnect', [])->assertOk();

        $site->refresh();
        $this->assertSame(SeoWpSite::STATUS_DISCONNECTED, $site->status);
        $this->assertNull($site->secret);
    }

    public function test_a_site_is_stale_when_it_has_been_silent_too_long(): void
    {
        $fresh = $this->connectedSite();
        $silent = $this->connectedSite();
        $silent->update(['last_seen_at' => now()->subHours(SeoWpSite::STALE_AFTER_HOURS + 1)]);

        $this->assertFalse($fresh->isStale());
        $this->assertTrue($silent->isStale());
    }
}
