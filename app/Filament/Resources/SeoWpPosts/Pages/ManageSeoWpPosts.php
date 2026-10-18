<?php

namespace App\Filament\Resources\SeoWpPosts\Pages;

use App\Filament\Resources\SeoWpPosts\SeoWpPostResource;
use App\Jobs\AnalyzeWpSiteJob;
use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageSeoWpPosts extends ManageRecords
{
    protected static string $resource = SeoWpPostResource::class;

    public function mount(): void
    {
        parent::mount();

        $discouraged = SeoWpSite::query()->with('project')->where('search_engines_discouraged', true)->get();

        if ($discouraged->isNotEmpty()) {
            Notification::make()
                ->title('«جلوگیری از ایندکس‌شدن توسط موتورهای جست‌وجو» روشن است')
                ->body('در این سایت‌ها گزینه‌ی «Discourage search engines» (وردپرس ← تنظیمات ← خواندن) روشن است و گوگل صفحه‌ها را از نتایج حذف می‌کند: '.$discouraged->pluck('project.name')->implode('، '))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function getSubheading(): ?string
    {
        $connected = SeoWpSite::query()->where('status', SeoWpSite::STATUS_CONNECTED)->count();
        $stale = SeoWpSite::query()->where('status', SeoWpSite::STATUS_CONNECTED)->get()->filter->isStale()->count();
        $withIssues = SeoWpPost::query()->whereJsonLength('issues', '>', 0)->count();

        $text = "{$connected} سایت متصل، ".SeoWpPost::query()->count()." صفحه، {$withIssues} صفحه با مشکل";

        return $stale > 0 ? $text." — {$stale} سایت مدتی است چیزی نفرستاده" : $text;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('analyzeAgain')
                ->label('تحلیل دوباره')
                ->icon(Heroicon::OutlinedArrowPath)
                ->tooltip('کلیک و ایمپرشن تازه‌ی Search Console، لینک‌های داخلی و مشکلات همه‌ی سایت‌های متصل را دوباره حساب می‌کند.')
                ->action(function () {
                    $sites = SeoWpSite::query()->where('status', SeoWpSite::STATUS_CONNECTED)->get();

                    foreach ($sites as $index => $site) {
                        AnalyzeWpSiteJob::dispatch($site->id)->delay(now()->addSeconds($index * 5));
                    }

                    Notification::make()->title($sites->count().' سایت برای تحلیل در صف قرار گرفت.')->success()->send();
                }),
        ];
    }
}
