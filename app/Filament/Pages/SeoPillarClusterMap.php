<?php

namespace App\Filament\Pages;

use App\Domain\Seo\PillarClusterMapBuilder;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoProject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * A single read-only view of every topic cluster ("pillar") for one
 * project, with all its keywords - real tracked ones (SeoKeywordProfile),
 * AI-suggested ones (SeoKeywordSuggestion), and content items - grouped
 * together instead of scattered across three separate resource tables.
 * Lets an SEO operator spot, at a glance, which pillar already has too
 * much overlapping content and which is still empty - see
 * App\Domain\Seo\PillarClusterMapBuilder for how the grouping and the
 * lexical-overlap warnings are built.
 */
class SeoPillarClusterMap extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.seo-pillar-cluster-map';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'نقشه موضوعی (پیلار-کلاستر)';

    protected static ?string $title = 'نقشه موضوعی (پیلار-کلاستر)';

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public static function accessControlPermission(): string
    {
        return 'seo-pillar-cluster-map.access';
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

    public function map(): ?Collection
    {
        $project = $this->selectedProject();

        return $project ? app(PillarClusterMapBuilder::class)->build($project) : null;
    }

    /**
     * A d3.hierarchy()-shaped tree for the radial view: project name at
     * the root, one branch per topic cluster, one leaf per keyword. A
     * leaf's `hasWarning` flag drives its dot color on the client - true
     * when that exact keyword text appears in its cluster's lexical
     * cannibalization warnings (see PillarClusterMapBuilder), since real
     * search volume isn't data this app has.
     *
     * @return array<string, mixed>|null
     */
    public function treeData(): ?array
    {
        $project = $this->selectedProject();
        $map = $this->map();

        if (! $project || ! $map) {
            return null;
        }

        return [
            'name' => $project->name,
            'children' => $map->map(function (array $group): array {
                $keywordsWithWarnings = collect($group['warnings'])
                    ->flatMap(fn (array $warning) => [$warning['a'], $warning['b']])
                    ->unique();

                $keywords = $group['profiles']->pluck('query')
                    ->merge($group['suggestions']->pluck('keyword'))
                    ->unique()
                    ->values();

                return [
                    'name' => $group['name'],
                    'children' => $keywords->map(fn (string $keyword) => [
                        'name' => $keyword,
                        'hasWarning' => $keywordsWithWarnings->contains($keyword),
                    ])->all(),
                ];
            })->values()->all(),
        ];
    }
}
