<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\TripPlannerSetting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            'plans_per_day_limit' => $setting->plans_per_day_limit,
            'create_followup_tasks' => $setting->create_followup_tasks,
            'send_reminders' => $setting->send_reminders,
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
                TextInput::make('plans_per_day_limit')
                    ->label('حداکثر برنامه‌ی جدید برای هر مهمان در شبانه‌روز')
                    ->helperText('هر ساخت برنامه یک درخواست هوش مصنوعی (و هزینه) است؛ این سقف جلوی سوءاستفاده و هزینه‌ی بی‌مورد را می‌گیرد.')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->required(),
                Toggle::make('create_followup_tasks')
                    ->label('برای مهمانی که برنامه ساخته ولی رزرو ندارد، وظیفه‌ی پیگیری بساز')
                    ->helperText('وظیفه برای ادمین‌ها در پرونده‌ی مهمان ساخته می‌شود، حداکثر یک بار برای هر مهمان در هفته.'),
                Toggle::make('send_reminders')
                    ->label('یک روز قبل از شروع سفر پیامک یادآوری بفرست')
                    ->helperText('نیاز به فعال بودن سرویس پیامک دارد و هزینه‌ی پیامک دارد.'),
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
