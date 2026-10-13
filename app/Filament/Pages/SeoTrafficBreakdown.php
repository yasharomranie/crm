<?php

namespace App\Filament\Pages;

use App\Domain\Seo\SeoTrafficBreakdownService;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoProject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Reads App\Models\SeoCountryMetric/SeoSearchTypeMetric/
 * SeoSearchAppearanceMetric - synced daily by SyncSearchConsoleDataJob
 * but never read anywhere else (no Resource, no widget) - the same
 * project-selector shape App\Filament\Pages\SeoActionQueue and
 * App\Filament\Pages\SeoSitemapAnalyzer already use.
 */
class SeoTrafficBreakdown extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.seo-traffic-breakdown';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'ترکیب ترافیک';

    protected static ?string $title = 'ترکیب ترافیک';

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public static function accessControlPermission(): string
    {
        return 'seo-traffic-breakdown.access';
    }

    /**
     * @return Collection<int, SeoProject>
     */
    public function projects(): Collection
    {
        return SeoProject::orderBy('name')->get();
    }

    public function selectedProject(): ?SeoProject
    {
        return $this->projectId ? SeoProject::find($this->projectId) : null;
    }

    /**
     * @return Collection<int, array{country: string, clicks: int, impressions: int}>|null
     */
    public function topCountries(): ?Collection
    {
        $project = $this->selectedProject();

        return $project ? app(SeoTrafficBreakdownService::class)->topCountries($project) : null;
    }

    /**
     * @return Collection<int, array{search_type: string, clicks: int, impressions: int}>|null
     */
    public function bySearchType(): ?Collection
    {
        $project = $this->selectedProject();

        return $project ? app(SeoTrafficBreakdownService::class)->bySearchType($project) : null;
    }

    /**
     * @return Collection<int, array{search_appearance: string, clicks: int, impressions: int}>|null
     */
    public function bySearchAppearance(): ?Collection
    {
        $project = $this->selectedProject();

        return $project ? app(SeoTrafficBreakdownService::class)->bySearchAppearance($project) : null;
    }
}
