<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoKeywordTrend;
use App\Enums\SeoSearchIntent;
use App\Filament\Widgets\SeoKeywordProfileStatsWidget;
use App\Models\SeoKeywordProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * «کلمات کلیدی»'s own stats row, mirroring SeoDashboardTest's structure
 * for the same kind of page-scoped widget.
 */
class SeoKeywordProfileStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_widget_renders_with_real_keyword_data(): void
    {
        SeoKeywordProfile::factory()->create();

        $this->actingAs(User::factory()->create());

        Livewire::test(SeoKeywordProfileStatsWidget::class)->assertOk();
    }

    /**
     * Regression guard matching SeoDashboardTest's own: a widget mounted
     * directly via Livewire::test() never hydrates (that only happens on
     * a *second* request against an already-mounted component), so this
     * forces that round trip.
     */
    public function test_the_widget_survives_a_second_hydration_not_just_the_first_mount(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SeoKeywordProfileStatsWidget::class)->call('$refresh')->assertOk();
    }

    public function test_the_widget_renders_fine_with_no_keyword_profiles_at_all(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SeoKeywordProfileStatsWidget::class)->assertOk();
    }

    /**
     * Reads the Stat objects directly (Filament\Widgets\StatsOverviewWidget\
     * Stat::getValue()/getLabel() are both public) rather than asserting on
     * rendered HTML digits, which single/low-digit counts like these would
     * make unreliable - a coincidental "2" elsewhere on the page would
     * pass a broken widget just as easily as a correct one.
     */
    public function test_the_stats_correctly_sum_and_count_across_all_profiles(): void
    {
        SeoKeywordProfile::factory()->create(['clicks_28d' => 40, 'impressions_28d' => 400, 'current_position' => 3.0, 'trend' => SeoKeywordTrend::Rising, 'intent' => SeoSearchIntent::Local]);
        SeoKeywordProfile::factory()->create(['clicks_28d' => 10, 'impressions_28d' => 300, 'current_position' => 12.0, 'trend' => SeoKeywordTrend::Falling, 'intent' => SeoSearchIntent::Informational]);
        SeoKeywordProfile::factory()->create(['clicks_28d' => 5, 'impressions_28d' => 100, 'current_position' => 4.5, 'trend' => SeoKeywordTrend::Stable, 'intent' => SeoSearchIntent::Informational]);

        $widget = new SeoKeywordProfileStatsWidget;
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);
        $stats = collect($method->invoke($widget))->keyBy(fn ($stat) => $stat->getLabel());

        $this->assertSame('۵۵', $stats['تعداد کلیک سایت']->getValue());
        $this->assertSame('۸۰۰', $stats['تعداد نمایش']->getValue());
        $this->assertSame('۲', $stats['کلمات با رتبه ۱ تا ۵']->getValue());
        $this->assertSame('۱', $stats['کلمات در حال رشد']->getValue());
        $this->assertSame('۱', $stats['کلمات در حال افت']->getValue());
        $this->assertSame('۱', $stats['کلمات ثابت']->getValue());
        $this->assertSame('۱', $stats['کلمات با نیت محلی']->getValue());
        $this->assertSame('۴۰ کلیک ۲۸ روز اخیر', $stats['کلمات با نیت محلی']->getDescription());
    }

    public function test_the_keyword_profiles_page_shows_the_stats_widget(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get(route('filament.admin.resources.seo-keyword-profiles.index'))
            ->assertOk();

        $response->assertSeeLivewire(SeoKeywordProfileStatsWidget::class);
    }

    public function test_the_widget_is_not_visible_on_the_main_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/admin')
            ->assertOk();

        $response->assertDontSeeLivewire(SeoKeywordProfileStatsWidget::class);
    }
}
