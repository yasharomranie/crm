<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\GuideSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The on/off switch of the smart guide experiment and the numbers visitors'
 * phones match with. Off by default: while it is off, visitors get nothing and
 * only signed-in staff can open the guide to test it.
 */
class GuideSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.guide-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = 'راهنمای هوشمند گردشگری';

    protected static ?string $navigationLabel = 'تنظیمات راهنما';

    protected static ?string $title = 'تنظیمات راهنمای هوشمند (آزمایشی)';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'guide-settings.access';
    }

    public function mount(): void
    {
        $setting = GuideSetting::current();

        $this->form->fill($setting->only(['is_enabled', 'similarity_threshold', 'min_margin', 'frame_interval_ms', 'min_reference_images']));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('راهنمای هوشمند برای گردشگران فعال باشد')
                    ->helperText('خاموش، یعنی گردشگران هیچ چیز نمی‌بینند و فقط کارکنانِ واردشده می‌توانند آزمایش کنند.'),
                TextInput::make('similarity_threshold')
                    ->label('آستانهٔ شباهت (۰ تا ۱)')
                    ->helperText('شباهتی کمتر از این عدد «شناسایی نشد» حساب می‌شود. عدد درست را «آزمون دقت» با داده‌های واقعی تعیین می‌کند؛ ۰٫۷ فقط شروع است.')
                    ->numeric()->minValue(0)->maxValue(1)->step(0.01)->required(),
                TextInput::make('min_margin')
                    ->label('حداقل فاصله با گزینهٔ دوم (۰ تا ۱)')
                    ->helperText('اگر دو شیء تقریباً به یک اندازه شبیه باشند، نتیجه رد می‌شود تا اشتباه نشان داده نشود.')
                    ->numeric()->minValue(0)->maxValue(1)->step(0.01)->required(),
                TextInput::make('frame_interval_ms')
                    ->label('فاصلهٔ اسکن (میلی‌ثانیه)')
                    ->helperText('هر چند میلی‌ثانیه یک تصویر از دوربین بررسی شود.')
                    ->numeric()->integer()->minValue(300)->maxValue(10000)->required(),
                TextInput::make('min_reference_images')
                    ->label('حداقل تصویر مرجع برای آماده‌شدن شیء')
                    ->numeric()->integer()->minValue(1)->maxValue(50)->required(),
            ]);
    }

    public function save(): void
    {
        GuideSetting::current()->update($this->form->getState());

        Notification::make()->title('تنظیمات ذخیره شد.')->success()->send();
    }
}
