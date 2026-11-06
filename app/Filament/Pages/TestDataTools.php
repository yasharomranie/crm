<?php

namespace App\Filament\Pages;

use App\Domain\System\DemoDataGenerator;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Makes (and removes) a small set of marked sample records, so the screens
 * can be tried on the live server without touching real data.
 */
class TestDataTools extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'filament.pages.test-data-tools';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $navigationLabel = 'دادهٔ آزمایشی';

    protected static ?string $title = 'دادهٔ آزمایشی';

    public static function accessControlPermission(): string
    {
        return 'test-data-tools.access';
    }

    /**
     * @return array<string, int>
     */
    public function getCounts(): array
    {
        return app(DemoDataGenerator::class)->counts();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTestData')
                ->label('ساخت دادهٔ آزمایشی')
                ->icon(Heroicon::OutlinedPlus)
                ->requiresConfirmation()
                ->modalHeading('ساخت دادهٔ آزمایشی')
                ->modalDescription('چند اتاق، مهمان، رزرو، یک واحد بسته‌شده، یک شرکت و دو معامله با نشانهٔ «[تست]» ساخته می‌شود. به هیچ رکورد موجودی دست زده نمی‌شود.')
                ->action(function (): void {
                    $result = app(DemoDataGenerator::class)->create();

                    $result['created']
                        ? Notification::make()->title('دادهٔ آزمایشی ساخته شد.')->success()->send()
                        : Notification::make()->title('دادهٔ آزمایشی از قبل وجود دارد.')->body('برای ساخت دوباره، اول آن را پاک کنید.')->warning()->send();
                }),
            Action::make('removeTestData')
                ->label('پاک کردن دادهٔ آزمایشی')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('پاک کردن دادهٔ آزمایشی')
                ->modalDescription('فقط رکوردهایی که نامشان با «[تست]» شروع می‌شود پاک می‌شوند؛ هیچ دادهٔ دیگری پاک نمی‌شود.')
                ->action(function (): void {
                    $result = app(DemoDataGenerator::class)->remove();

                    $notification = Notification::make()->title('دادهٔ آزمایشی پاک شد.')->success();

                    if ($result['kept_rooms'] > 0) {
                        $notification->body("{$result['kept_rooms']} اتاق آزمایشی نگه داشته شد چون رزرو واقعی دارد.");
                    }

                    $notification->send();
                }),
        ];
    }
}
