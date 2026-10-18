<?php

namespace App\Filament\Pages;

use App\Domain\Ticketing\TicketingReport;
use App\Domain\Ticketing\TicketingReportExport;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class TicketingReportsPage extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.ticketing-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|\UnitEnum|null $navigationGroup = 'گیشه بلیط';

    protected static ?string $navigationLabel = 'گزارش‌های گیشه';

    protected static ?string $title = 'گزارش‌های گیشه بلیت';

    /** today | 7 | 30 */
    public string $period = 'today';

    public static function accessControlPermission(): string
    {
        return 'ticketing-reports.access';
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $to = now();
        $from = match ($this->period) {
            '7' => now()->subDays(6)->startOfDay(),
            '30' => now()->subDays(29)->startOfDay(),
            default => now()->startOfDay(),
        };

        return app(TicketingReport::class)->build($from, Carbon::instance($to));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('خروجی اکسل')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function () {
                    $path = app(TicketingReportExport::class)->render($this->report());

                    return response()->streamDownload(function () use ($path) {
                        readfile($path);
                        @unlink($path);
                    }, 'گزارش-گیشه-'.now()->format('Y-m-d').'.xlsx', [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),
        ];
    }

    public function toman(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
