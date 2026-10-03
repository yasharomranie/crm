<?php

namespace App\Filament\Resources\SeoContentItems\Pages;

use App\Domain\Seo\ContentCalendarPlannerService;
use App\Filament\Resources\SeoContentItems\SeoContentItemResource;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ListSeoContentItems extends ListRecords
{
    protected static string $resource = SeoContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // "چه زمانی و چقدر" - نیمه‌ی دیگر (پیشنهاد "چه چیزی بنویسیم") به
            // فاز Content Gap سپرده شده. فقط بک‌لاگ موجود (ایده/برنامه‌ی
            // بدون مهلت + پیشنهاد کلمه‌کلیدی تأییدشده‌ی بدون محتوا) را در
            // بازه‌ی انتخاب‌شده پخش می‌کند - نه ایده‌ی تازه می‌سازد.
            Action::make('proposeSchedule')
                ->label('پیشنهاد زمان‌بندی')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('info')
                ->schema([
                    Select::make('seo_project_id')
                        ->label('پروژه')
                        ->options(fn () => SeoProject::pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    DatePicker::make('period_start')
                        ->label('شروع بازه')
                        ->jalali()
                        ->displayFormat('Y/m/d')
                        ->default(now())
                        ->required(),
                    DatePicker::make('period_end')
                        ->label('پایان بازه')
                        ->jalali()
                        ->displayFormat('Y/m/d')
                        ->default(now()->addMonth())
                        ->required()
                        ->afterOrEqual('period_start'),
                    TextInput::make('volume')
                        ->label('تعداد (اختیاری)')
                        ->helperText('اگر خالی بماند، بر اساس سرعت انتشار ۳ ماه اخیر پروژه محاسبه می‌شود - یا ۴ مورد در ماه اگر سابقه‌ای نباشد.')
                        ->numeric()
                        ->minValue(1),
                ])
                ->action(function (array $data) {
                    $project = SeoProject::findOrFail($data['seo_project_id']);
                    $planner = app(ContentCalendarPlannerService::class);

                    $proposal = $planner->proposeSchedule(
                        $project,
                        Carbon::parse($data['period_start']),
                        Carbon::parse($data['period_end']),
                        filled($data['volume'] ?? null) ? (int) $data['volume'] : null,
                    );

                    if ($proposal->isEmpty()) {
                        Notification::make()
                            ->title('چیزی برای زمان‌بندی پیدا نشد.')
                            ->body('نه آیتم محتوای بدون مهلت، نه پیشنهاد کلمه‌کلیدی تأییدشده‌ی بدون محتوا برای این پروژه موجود نیست.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $applied = $planner->applySchedule($project, $proposal);

                    Notification::make()
                        ->title("{$applied} مورد در تقویم محتوایی زمان‌بندی شد.")
                        ->body($proposal->map(fn (array $row) => "- {$row['title']}: {$row['suggested_due_date']->translatedFormat('Y/m/d')}")->implode("\n"))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
