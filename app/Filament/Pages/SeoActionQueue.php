<?php

namespace App\Filament\Pages;

use App\Domain\Seo\SeoActionItem;
use App\Domain\Seo\SeoActionQueueService;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoProject;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * One priority-ordered list of everything مرکز سئو thinks is worth doing
 * for a project right now - merging keyword/URL opportunities and
 * technical audit issues (and, per the project roadmap, future sources
 * like content gaps) via SeoActionQueueService, instead of making the
 * project owner check N separate resource tables to find out what to
 * work on next.
 */
class SeoActionQueue extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.seo-action-queue';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'صف اقدام';

    protected static ?string $title = 'صف اقدام';

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public static function accessControlPermission(): string
    {
        return 'seo-action-queue.access';
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
     * @return Collection<int, SeoActionItem>|null
     */
    public function items(): ?Collection
    {
        $project = $this->selectedProject();

        return $project ? app(SeoActionQueueService::class)->items($project) : null;
    }
}
