<?php

namespace App\Filament\Pages;

use App\Domain\Seo\SitemapAnalyzerService;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Resources\SeoProjects\SeoProjectResource;
use App\Models\SeoProject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The project roadmap's "فاز ۱": everything SitemapMonitorService has
 * synced from a project's sitemap.xml, reported properly - health
 * numbers, a growth chart, and a per-page table joined with the Search
 * Console data this app already has - instead of the cramped section
 * this used to be on the project edit form (see that page's own commit
 * history for why it moved).
 */
class SeoSitemapAnalyzer extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.seo-sitemap-analyzer';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'تحلیل‌گر Sitemap';

    protected static ?string $title = 'تحلیل‌گر Sitemap';

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public static function accessControlPermission(): string
    {
        return 'seo-sitemap-analyzer.access';
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

    public function editProjectUrl(): ?string
    {
        $project = $this->selectedProject();

        return $project ? SeoProjectResource::getUrl('edit', ['record' => $project]) : null;
    }

    public function overview(): ?array
    {
        $project = $this->selectedProject();

        return $project ? app(SitemapAnalyzerService::class)->overview($project) : null;
    }

    /**
     * @return array<int, array{label: string, count: int}>|null
     */
    public function growthSeries(): ?array
    {
        $project = $this->selectedProject();

        return $project ? app(SitemapAnalyzerService::class)->growthSeries($project) : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>|null
     */
    public function pages(): ?Collection
    {
        $project = $this->selectedProject();

        return $project ? app(SitemapAnalyzerService::class)->pages($project) : null;
    }
}
