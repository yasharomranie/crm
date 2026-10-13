<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DealSourceChart;
use App\Filament\Widgets\DealWinLossTrendChart;
use App\Filament\Widgets\SalesFunnelChart;
use App\Filament\Widgets\SalesPipelineStatsOverview;
use App\Filament\Widgets\SeoKeywordProfileStatsWidget;
use App\Filament\Widgets\SeoOverviewStatsWidget;
use App\Filament\Widgets\SeoUpcomingContentWidget;
use App\Filament\Widgets\StaleDealsWidget;
use App\Filament\Widgets\TopSalesRepsWidget;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Filament\Pages\Dashboard::getWidgets() renders literally every widget
 * registered on the panel (Filament::getWidgets()), which would include
 * any other page's own report row (SalesPipeline::getHeaderWidgets(),
 * SeoDashboard::getHeaderWidgets(), ...) unless it's excluded here.
 *
 * An earlier attempt gated those widgets with a canView() that checked the
 * current request's route name - that broke as soon as a widget hydrated
 * over Livewire's own update endpoint instead of the page's route (lazy
 * loading, or clicking any action inside the widget, e.g. its Excel/PDF
 * export buttons), which made canView() flip to false mid-session and
 * Filament show a "couldn't load the page" error toast. Excluding the
 * widgets by class right here needs no request inspection at all, so it
 * can't suffer from that.
 */
class Dashboard extends BaseDashboard
{
    private const SALES_PIPELINE_ONLY_WIDGETS = [
        SalesPipelineStatsOverview::class,
        SalesFunnelChart::class,
        DealSourceChart::class,
        DealWinLossTrendChart::class,
        TopSalesRepsWidget::class,
        StaleDealsWidget::class,
    ];

    /** Scoped to their own مرکز سئو pages (SeoDashboard, Keyword Profiles), never the main dashboard. */
    private const SEO_PAGE_ONLY_WIDGETS = [
        SeoOverviewStatsWidget::class,
        SeoUpcomingContentWidget::class,
        SeoKeywordProfileStatsWidget::class,
    ];

    public function getWidgets(): array
    {
        return array_values(array_diff(
            parent::getWidgets(),
            self::SALES_PIPELINE_ONLY_WIDGETS,
            self::SEO_PAGE_ONLY_WIDGETS,
        ));
    }

    /**
     * This host has no persistent queue worker (routes/console.php
     * explains why: no Supervisor/terminal access to run `queue:work` as
     * a daemon), so every background job - OCR on a scanned PDF, a
     * WebPush notification, a scheduled report - normally waits for the
     * schedule:run cron to drain it, roughly once a minute. If that cron
     * itself isn't actually configured (which nobody without terminal
     * access can check or fix on their own), jobs sit in the queue
     * forever with no visible error. This button is the web-only escape
     * hatch: the same `queue:work --stop-when-empty` command the cron
     * already runs, triggered on demand and detached from this request
     * (Process::start(), not ->run()) so a slow job in the queue - OCR
     * on a long document can take minutes - doesn't hang the button
     * click or risk a PHP execution-time timeout.
     *
     * PHP_BINARY is the wrong source for the CLI path here - under
     * php-fpm (how this action actually runs) it resolves to the fpm
     * binary, not something `artisan` can be run through.
     * PhpExecutableFinder exists specifically to find the real CLI
     * executable regardless of which SAPI is currently running.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('processQueuedJobs')
                ->label('پردازش دستی کارهای در صف')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->tooltip('کارهای پس‌زمینه (مثل OCR فایل اسکن‌شده) معمولاً هر دقیقه خودکار پردازش می‌شوند. اگر یکی گیر کرده یا می‌خواهید الان اجرا شود، این دکمه را بزنید.')
                ->action(function () {
                    if (! function_exists('proc_open')) {
                        Notification::make()
                            ->title('این سرور اجازهٔ اجرای فرآیند جداگانه (proc_open) را نمی‌دهد.')
                            ->body('این یعنی نه این دکمه کار می‌کند، نه OCR فایل‌های اسکن‌شده - هر دو به یک قابلیت نیاز دارند. باید هاست/میزبان خود را برای فعال‌کردن proc_open، یا تنظیم یک کرون سرور-سطح برای queue:work، هماهنگ کنید.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    try {
                        $phpBinary = (new PhpExecutableFinder)->find() ?: 'php';

                        $process = new Process([$phpBinary, 'artisan', 'queue:work', '--stop-when-empty', '--max-time=50'], base_path());
                        $process->setTimeout(null);
                        $process->disableOutput();
                        $process->start();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('اجرای پردازش صف با خطا مواجه شد.')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('پردازش صف کارها شروع شد.')
                        ->body('اگر فایلی در حال OCR بود، تا چند دقیقهٔ دیگر تمام می‌شود - صفحه را بعداً دوباره باز کنید تا وضعیت را ببینید.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
