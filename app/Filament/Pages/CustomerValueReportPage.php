<?php

namespace App\Filament\Pages;

use App\Domain\Crm\CustomerValueReport;
use App\Enums\TaskType;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\Guest;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

class CustomerValueReportPage extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.customer-value-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $navigationLabel = 'ارزش و ریزش مشتری';

    protected static ?string $title = 'ارزش طول عمر و ریزش مشتری';

    public static function accessControlPermission(): string
    {
        return 'customer-value-report.access';
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return app(CustomerValueReport::class)->build();
    }

    public function toman(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('followUpAtRisk')
                ->label('ساخت وظیفه‌ی پیگیری برای مشتریان در معرض ریزش')
                ->icon(Heroicon::OutlinedPhone)
                ->requiresConfirmation()
                ->modalDescription('برای بیست مشتری ارزشمندِ در معرض ریزش یک وظیفه‌ی پیگیری به نام شما ساخته می‌شود؛ مشتری‌ای که در دو هفته‌ی اخیر همین وظیفه را داشته، دوباره نمی‌گیرد.')
                ->visible(fn () => (bool) auth()->user()?->can('tasks.create'))
                ->action(function (): void {
                    $created = 0;

                    foreach (array_slice($this->report()['at_risk_guests'], 0, 20) as $row) {
                        /** @var Guest $guest */
                        $guest = $row['guest'];

                        $alreadyAsked = $guest->followUpTasks()
                            ->where('title', 'like', 'بازگرداندن مشتری%')
                            ->where('created_at', '>=', now()->subWeeks(2))
                            ->exists();

                        if ($alreadyAsked) {
                            continue;
                        }

                        Task::create([
                            'title' => "بازگرداندن مشتری در معرض ریزش: «{$guest->name}» ".Number::toPersian($row['days_since_last']).' روز است اقامت نکرده',
                            'task_type' => TaskType::CrmFollowUp,
                            'source_type' => Guest::class,
                            'source_id' => $guest->id,
                            'assigned_to' => auth()->id(),
                            'due_at' => now()->addDays(2),
                        ]);
                        $created++;
                    }

                    Notification::make()->title("{$created} وظیفه‌ی پیگیری ساخته شد")->success()->send();
                }),
        ];
    }
}
