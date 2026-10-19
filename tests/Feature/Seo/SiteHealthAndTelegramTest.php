<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\GoogleAutocompleteClient;
use App\Domain\Seo\SeoAlertService;
use App\Domain\Seo\SiteHealth\SiteHealthMonitor;
use App\Domain\Seo\SiteHealth\SslExpiryReader;
use App\Domain\Seo\TelegramNotifier;
use App\Domain\Seo\WpConnector\WpContentBrief;
use App\Domain\Seo\WpConnector\WpFleetStatus;
use App\Domain\Seo\WpConnector\WpPluginPackage;
use App\Filament\Pages\SeoAlertSettings;
use App\Models\SeoAlertSetting;
use App\Models\SeoProject;
use App\Models\SeoSiteCheck;
use App\Models\SeoWpSite;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteHealthAndTelegramTest extends TestCase
{
    use RefreshDatabase;

    private ?CarbonImmutable $sslEnd = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sslEnd = now()->addDays(90)->toImmutable();

        $this->app->instance(SslExpiryReader::class, new class($this) extends SslExpiryReader
        {
            public function __construct(private readonly SiteHealthAndTelegramTest $test) {}

            public function expiresAt(string $host, int $port = 443): ?CarbonImmutable
            {
                return $this->test->currentSslEnd();
            }
        });
    }

    public function currentSslEnd(): ?CarbonImmutable
    {
        return $this->sslEnd;
    }

    private function telegramOn(): SeoAlertSetting
    {
        $setting = SeoAlertSetting::current();
        $setting->update([
            'is_enabled' => true,
            'notify_telegram' => true,
            'telegram_bot_token' => '123:ABC',
            'telegram_chat_id' => '-100500',
        ]);

        return $setting->fresh();
    }

    private function telegramRequests(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), '/bot123:ABC/sendMessage'))->count();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_notifier_posts_the_text_to_the_chat_with_the_token(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $sent = app(TelegramNotifier::class)->send('سلام', $this->telegramOn());

        $this->assertTrue($sent);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.telegram.org/bot123:ABC/sendMessage'
            && $request['chat_id'] === '-100500'
            && $request['text'] === 'سلام');
    }

    public function test_the_notifier_uses_a_relay_address_when_one_is_set(): void
    {
        Http::fake(['relay.example.test/*' => Http::response(['ok' => true])]);
        $setting = $this->telegramOn();
        $setting->update(['telegram_api_base' => 'https://relay.example.test/']);

        $this->assertTrue(app(TelegramNotifier::class)->send('سلام', $setting->fresh()));
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://relay.example.test/bot123:ABC/'));
    }

    public function test_the_notifier_does_nothing_when_telegram_is_not_set_up(): void
    {
        Http::fake();

        $this->assertFalse(app(TelegramNotifier::class)->send('سلام', SeoAlertSetting::current()));
        Http::assertNothingSent();
    }

    public function test_the_notifier_survives_a_failed_send(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response('blocked', 403)]);

        $this->assertFalse(app(TelegramNotifier::class)->send('سلام', $this->telegramOn()));
    }

    public function test_an_alert_reaches_telegram_only_when_alerts_and_telegram_are_on(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $project = SeoProject::factory()->create();

        app(SeoAlertService::class)->notifyGoogleConnectionError($project, 'خطا');
        $this->assertSame(0, $this->telegramRequests());

        $this->telegramOn();
        app(SeoAlertService::class)->notifyGoogleConnectionError($project, 'خطا');
        $this->assertSame(1, $this->telegramRequests());

        SeoAlertSetting::current()->update(['is_enabled' => false]);
        app(SeoAlertService::class)->notifyGoogleConnectionError($project, 'خطا');
        $this->assertSame(1, $this->telegramRequests());
    }

    public function test_the_settings_page_keeps_the_saved_token_when_left_blank_and_sends_a_test(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->telegramOn();

        Livewire::actingAs($this->admin())
            ->test(SeoAlertSettings::class)
            ->set('data.telegram_chat_id', '-100999')
            ->set('data.telegram_bot_token', '')
            ->call('save')
            ->callAction('sendTelegramTest');

        $setting = SeoAlertSetting::current();
        $this->assertSame('123:ABC', $setting->telegram_bot_token);
        $this->assertSame('-100999', $setting->telegram_chat_id);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/bot123:ABC/sendMessage') && str_contains($request['text'], 'آزمایشی'));
    }

    public function test_a_site_is_called_down_only_after_two_failed_checks_and_alerted_once(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
            'down.example.test*' => Http::response('', 503),
        ]);
        $this->telegramOn();
        $project = SeoProject::factory()->create(['website_url' => 'https://down.example.test']);

        app(SiteHealthMonitor::class)->run();
        $this->assertSame(0, $this->telegramRequests());
        $this->assertFalse($project->siteCheck->fresh()->is_up);

        app(SiteHealthMonitor::class)->run();
        $this->assertSame(1, $this->telegramRequests());

        app(SiteHealthMonitor::class)->run();
        $this->assertSame(1, $this->telegramRequests());
        $this->assertSame(3, $project->siteCheck->fresh()->consecutive_failures);
    }

    public function test_a_site_that_answers_again_is_announced_and_a_4xx_still_counts_as_up(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
            'back.example.test*' => Http::sequence()->push('', 500)->push('', 500)->push('', 403),
        ]);
        $this->telegramOn();
        $project = SeoProject::factory()->create(['website_url' => 'https://back.example.test']);

        app(SiteHealthMonitor::class)->run();
        app(SiteHealthMonitor::class)->run();
        $this->assertSame(1, $this->telegramRequests());

        app(SiteHealthMonitor::class)->run();

        $check = $project->siteCheck->fresh();
        $this->assertTrue($check->is_up);
        $this->assertNull($check->alerted_down_at);
        $this->assertSame(0, $check->consecutive_failures);
        $this->assertSame(2, $this->telegramRequests());
    }

    public function test_the_certificate_is_alerted_once_per_threshold_and_resets_after_renewal(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true]), '*' => Http::response('ok')]);
        $this->telegramOn();
        $project = SeoProject::factory()->create(['website_url' => 'https://ssl.example.test']);

        $this->sslEnd = now()->addDays(40)->toImmutable();
        app(SiteHealthMonitor::class)->run();
        $this->assertSame(0, $this->telegramRequests());

        $this->sslEnd = now()->addDays(10)->addHour()->toImmutable();
        app(SiteHealthMonitor::class)->run();
        app(SiteHealthMonitor::class)->run();
        $this->assertSame(1, $this->telegramRequests());

        $this->sslEnd = now()->addDays(6)->addHour()->toImmutable();
        app(SiteHealthMonitor::class)->run();
        $this->assertSame(2, $this->telegramRequests());

        $this->sslEnd = now()->addDays(90)->toImmutable();
        app(SiteHealthMonitor::class)->run();
        $this->assertNull($project->siteCheck->fresh()->alerted_ssl_days);

        $this->sslEnd = now()->addDays(10)->addHour()->toImmutable();
        app(SiteHealthMonitor::class)->run();
        $this->assertSame(3, $this->telegramRequests());
    }

    public function test_inactive_projects_and_projects_without_an_address_are_skipped(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        SeoProject::factory()->create(['is_active' => false]);
        SeoProject::factory()->create(['website_url' => '']);
        $active = SeoProject::factory()->create();

        $this->assertSame(1, app(SiteHealthMonitor::class)->run());
        $this->assertNotNull($active->siteCheck);
    }

    public function test_the_command_runs_the_monitor(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        SeoProject::factory()->create();

        $this->assertSame(0, Artisan::call('seo:check-site-health'));
        $this->assertSame(1, SeoSiteCheck::count());
    }

    public function test_the_fleet_shows_a_down_site_first_and_a_certificate_near_its_end(): void
    {
        $down = SeoWpSite::factory()->connected()->create();
        SeoSiteCheck::factory()->create(['seo_project_id' => $down->seo_project_id, 'is_up' => false, 'consecutive_failures' => 2]);
        $expiring = SeoWpSite::factory()->connected()->create();
        SeoSiteCheck::factory()->create(['seo_project_id' => $expiring->seo_project_id, 'ssl_expires_at' => now()->addDays(5)]);
        SeoWpSite::factory()->connected()->create(['plugin_version' => app(WpPluginPackage::class)->version()]);

        $overview = app(WpFleetStatus::class)->overview();

        $this->assertSame(1, $overview['counts']['down']);
        $this->assertSame(1, $overview['counts']['ssl']);
        $this->assertSame(['down', 'ssl', 'ok'], $overview['rows']->pluck('state')->all());
    }

    public function test_autocomplete_merges_variants_hides_the_seed_and_caches_the_answer(): void
    {
        config(['seo.autocomplete.enabled' => true]);
        Cache::flush();
        Http::fake(['suggestqueries.google.com/*' => Http::response(['q', ['غار علیصدر', 'قیمت بلیط غار علیصدر', 'ساعت بازدید غار علیصدر']])]);

        $suggestions = app(GoogleAutocompleteClient::class)->suggestionsFor('غار علیصدر');

        $this->assertSame(['قیمت بلیط غار علیصدر', 'ساعت بازدید غار علیصدر'], $suggestions);

        $requests = Http::recorded()->count();
        app(GoogleAutocompleteClient::class)->suggestionsFor('غار علیصدر');
        $this->assertSame($requests, Http::recorded()->count());
    }

    public function test_autocomplete_does_nothing_when_disabled(): void
    {
        Http::fake();

        $this->assertSame([], app(GoogleAutocompleteClient::class)->suggestionsFor('غار'));
        Http::assertNothingSent();
    }

    public function test_autocomplete_returns_nothing_when_google_blocks_the_host(): void
    {
        config(['seo.autocomplete.enabled' => true]);
        Cache::flush();
        Http::fake(['suggestqueries.google.com/*' => Http::response('blocked', 429)]);

        $this->assertSame([], app(GoogleAutocompleteClient::class)->suggestionsFor('غار'));
    }

    public function test_a_brief_lists_what_people_type_in_google(): void
    {
        config(['seo.autocomplete.enabled' => true]);
        Cache::flush();
        Http::fake(['suggestqueries.google.com/*' => Http::response(['q', ['آیا غار علیصدر شبانه باز است']])]);

        $brief = app(WpContentBrief::class)->build(SeoProject::factory()->create(), 'غار علیصدر');

        $this->assertStringContainsString('آیا غار علیصدر شبانه باز است', $brief);
    }
}
