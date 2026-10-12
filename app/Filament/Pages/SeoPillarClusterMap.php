<?php

namespace App\Filament\Pages;

use App\Domain\Seo\CompetitorContentGapService;
use App\Domain\Seo\PillarClusterMapBuilder;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTrackedKeyword;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
class SeoPillarClusterMap extends Page implements HasActions
{
    use HasAccessControlledPage;
    use InteractsWithActions;

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
     * Total cannibalization warnings across every cluster - shown next
     * to both tab labels so switching to "لیست خوشه‌ها" isn't needed
     * just to find out whether there's anything to look at.
     */
    public function totalWarningsCount(): int
    {
        return (int) $this->map()?->sum(fn (array $group) => count($group['warnings']));
    }

    /**
     * Total real keywords with a positive CTR growth-opportunity score
     * across every cluster (see treeData()'s `hasOpportunity` for the
     * same underlying signal).
     */
    public function totalOpportunitiesCount(): int
    {
        return (int) $this->map()?->sum(
            fn (array $group) => $group['profiles']
                ->filter(fn (SeoKeywordProfile $profile) => $profile->opportunity_score !== null && (float) $profile->opportunity_score > 0)
                ->count()
        );
    }

    /**
     * How many distinct competitors cover each topic cluster and with
     * how many pages (App\Domain\Seo\CompetitorContentGapService) - the
     * same signal that service uses to create a gap suggestion, surfaced
     * here too so an operator can see competitive pressure per cluster
     * at a glance, not only after a gap has already been flagged.
     *
     * @return Collection<int, array{competitor_count: int, page_count: int}>
     */
    public function competitorCoverageByCluster(): Collection
    {
        $project = $this->selectedProject();

        return $project ? app(CompetitorContentGapService::class)->coverageByCluster($project) : collect();
    }

    /**
     * A d3.hierarchy()-shaped tree for the radial view: project name at
     * the root, one branch per topic cluster, one leaf per keyword. A
     * leaf's `hasWarning` flag drives its dot color on the client - true
     * when that exact keyword text appears in its cluster's lexical
     * cannibalization warnings (see PillarClusterMapBuilder), since real
     * search volume isn't data this app has. `hasOpportunity` is
     * independent of that - true when the same query has a positive
     * SeoKeywordProfile::opportunity_score (a real CTR-improvement
     * opportunity from OpportunityEngine), so a keyword can carry either
     * signal, both, or neither; only real profiles can have it, never AI
     * suggestions, since they have no Search Console data yet.
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

        $competitorCoverage = $this->competitorCoverageByCluster();

        return [
            'name' => $project->name,
            'children' => $map->map(function (array $group, int|string $clusterId) use ($competitorCoverage): array {
                $keywordsWithWarnings = collect($group['warnings'])
                    ->flatMap(fn (array $warning) => [$warning['a'], $warning['b']])
                    ->unique();

                $keywordsWithOpportunity = $group['profiles']
                    ->filter(fn (SeoKeywordProfile $profile) => $profile->opportunity_score !== null && (float) $profile->opportunity_score > 0)
                    ->pluck('query');

                $keywords = $group['profiles']->pluck('query')
                    ->merge($group['suggestions']->pluck('keyword'))
                    ->unique()
                    ->values();

                return [
                    'name' => $group['name'],
                    'competitorCoverage' => $competitorCoverage->get($clusterId),
                    'children' => $keywords->map(fn (string $keyword) => [
                        'name' => $keyword,
                        'hasWarning' => $keywordsWithWarnings->contains($keyword),
                        'hasOpportunity' => $keywordsWithOpportunity->contains($keyword),
                    ])->all(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * A shortcut next to a suggested keyword's badge in the "لیست
     * خوشه‌ها" tab, so committing to write for it doesn't require a trip
     * to the keyword-suggestions resource first. Unlike that resource's
     * own "ایجاد آیتم محتوا" action, this one doesn't require the
     * suggestion to already be Approved - clicking it here IS the
     * operator's approval, so it sets that status too.
     */
    public function addSuggestionToContentCalendarAction(): Action
    {
        return Action::make('addSuggestionToContentCalendar')
            ->label('افزودن به تقویم محتوایی')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('info')
            ->size('xs')
            ->schema(fn (array $arguments): array => [
                TextInput::make('title')
                    ->label('عنوان محتوا')
                    ->default(SeoKeywordSuggestion::find($arguments['suggestionId'])?->keyword)
                    ->required()
                    ->maxLength(255),
                Select::make('assigned_to')
                    ->label('مسئول نگارش')
                    ->options(fn () => User::pluck('name', 'id')),
                DatePicker::make('due_date')
                    ->label('مهلت انجام')
                    ->jalali()
                    ->displayFormat('Y/m/d'),
            ])
            ->action(function (array $data, array $arguments): void {
                $suggestion = SeoKeywordSuggestion::findOrFail($arguments['suggestionId']);

                $suggestion->update(['status' => SeoKeywordSuggestionStatus::Approved]);

                SeoContentItem::create([
                    'seo_project_id' => $suggestion->seo_project_id,
                    'keyword_suggestion_id' => $suggestion->id,
                    'title' => $data['title'],
                    'target_keyword' => $suggestion->keyword,
                    'content_type' => $suggestion->suggested_content_type,
                    'assigned_to' => $data['assigned_to'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                ]);

                // So the moment this content goes live, its rank is already
                // being watched - no separate trip to the rank tracker to
                // remember to add it.
                SeoTrackedKeyword::firstOrCreate(
                    ['seo_project_id' => $suggestion->seo_project_id, 'keyword' => $suggestion->keyword],
                    ['is_active' => true],
                );

                Notification::make()->title('آیتم محتوا در تقویم محتوا ایجاد شد.')->success()->send();
            });
    }
}
