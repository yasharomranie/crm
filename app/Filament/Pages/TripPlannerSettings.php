<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\TripPlannerSetting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single TripPlannerSetting row) -
 * assistant_note is a free-text note an admin can change any time
 * ("این هفته جاده غار علی‌صدر بسته است") that App\Domain\TripPlanner\TripPlannerService
 * injects into every itinerary prompt it builds, no deploy needed. For a
 * single attraction, use its own "یادداشت برای دستیار هوشمند" field on
 * "جاذبه‌های گردشگری" instead - this note is for something that applies
 * to the whole trip planner, not one place.
 */
class TripPlannerSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.trip-planner-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'تریپ‌پلنر';

    protected static ?string $navigationLabel = 'تنظیمات دستیار سفر';

    protected static ?string $title = 'تنظیمات دستیار هوشمند تریپ‌پلنر';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'trip-planner-settings.access';
    }

    public function mount(): void
    {
        $setting = TripPlannerSetting::current();

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'assistant_note' => $setting->assistant_note,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('تریپ‌پلنر فعال باشد')
                    ->helperText('در صورت خاموش بودن، مهمانان نمی‌توانند برنامه جدید بسازند.'),
                Textarea::make('assistant_note')
                    ->label('یادداشت عمومی برای دستیار هوشمند')
                    ->rows(4)
                    ->helperText('این متن در تمام برنامه‌های جدید به دستیار هوشمند گفته می‌شود؛ مثلاً «این هفته جشنواره گل در شهر برگزار می‌شود» یا نکات فصلی/آب‌وهوایی.'),
            ]);
    }

    public function save(): void
    {
        TripPlannerSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات تریپ‌پلنر ذخیره شد.')->success()->send();
    }
}
