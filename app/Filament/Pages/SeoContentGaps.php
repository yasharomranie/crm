<?php

namespace App\Filament\Pages;

use App\Domain\Seo\WpConnector\WpContentBrief;
use App\Domain\Seo\WpConnector\WpCoverageGaps;
use App\Enums\SeoContentItemStatus;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\SeoContentItem;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Where a project's WordPress pages and its Search Console searches meet:
 * what people find the site for but no page targets (write something new),
 * and what a page already ranks for on page two or three (rewrite it). Each
 * row goes into the content calendar with one click. The analysis lives in
 * App\Domain\Seo\WpConnector\WpCoverageGaps.
 */
class SeoContentGaps extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.seo-content-gaps';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'خلأ پوشش محتوا';

    protected static ?string $title = 'خلأ پوشش محتوا';

    #[Url(as: 'project')]
    public ?int $projectId = null;

    public static function accessControlPermission(): string
    {
        return 'seo-content-gaps.access';
    }

    public function mount(): void
    {
        $this->projectId ??= SeoProject::query()->whereHas('wpSite')->orderBy('name')->value('id');
    }

    /**
     * @return Collection<int, SeoProject>
     */
    public function projects(): Collection
    {
        return SeoProject::orderBy('name')->get();
    }

    /**
     * @return array{has_pages: bool, has_searches: bool, uncovered: Collection<int, array<string, mixed>>, improve: Collection<int, array<string, mixed>>}|null
     */
    public function gaps(): ?array
    {
        $project = $this->projectId ? SeoProject::find($this->projectId) : null;

        return $project ? app(WpCoverageGaps::class)->analyze($project) : null;
    }

    public function addToCalendar(string $kind, string $query, ?int $postId = null): void
    {
        if (! auth()->user()?->can('seo-content-items.create')) {
            Notification::make()->title('اجازه‌ی افزودن به تقویم محتوا را ندارید.')->danger()->send();

            return;
        }

        $improve = $kind === 'improve';
        $post = $improve && $postId ? $this->gaps()['improve']->firstWhere('post_id', $postId) : null;

        $project = SeoProject::find($this->projectId);
        $target = $improve && $post ? SeoWpPost::find($postId) : null;
        $reason = $improve && $post
            ? "این عبارت در رتبه‌ی {$post['position']} است."
            : 'گوگل این عبارت را به سایت نشان می‌دهد ولی هیچ صفحه‌ای هدفش نیست.';

        SeoContentItem::create([
            'seo_project_id' => $this->projectId,
            'title' => $improve && $post ? 'بازنویسی: '.$post['post_title'] : 'مقاله‌ی تازه: '.$query,
            'target_keyword' => $query,
            'content_type' => $improve ? 'به‌روزرسانی مقاله' : 'مقاله وبلاگ',
            'status' => $improve ? SeoContentItemStatus::NeedsUpdate : SeoContentItemStatus::Idea,
            'notes' => $reason."\n".app(WpContentBrief::class)->build($project, $query, $target),
        ]);

        Notification::make()->title('به تقویم محتوا اضافه شد.')->success()->send();
    }
}
