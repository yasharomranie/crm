<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\CustomerAppNavItem;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Lets an admin customize the four fixed tabs of the وب‌اپ مشتریان's
 * bottom navigation bar (خانه/سفارش‌های من/کیف پول/باشگاه, see
 * App\Models\CustomerAppNavItem) - label text, icon, display order and
 * whether a tab shows at all. Each tab's underlying destination (route)
 * and whether it requires a signed-in guest are fixed in
 * CustomerAppNavItem::DEFAULTS and never editable here - this page only
 * ever writes label/icon/sort_order/is_visible, never a URL.
 */
class CustomerAppNavSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.customer-app-nav-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomRight;

    protected static string|\UnitEnum|null $navigationGroup = 'وب‌اپ مشتریان';

    protected static ?string $navigationLabel = 'منوی پایین اپ';

    protected static ?string $title = 'منوی پایین اپ مشتریان';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'customer-app-nav-settings.access';
    }

    public function mount(): void
    {
        CustomerAppNavItem::ensureDefaults();

        $items = CustomerAppNavItem::query()->orderBy('sort_order')->get()->keyBy('key');

        $this->form->fill([
            'items' => $items->map(fn (CustomerAppNavItem $item) => [
                'label' => $item->label,
                'icon' => $item->icon,
                'sort_order' => $item->sort_order,
                'is_visible' => $item->is_visible,
            ])->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $iconOptions = CustomerAppNavItem::ICONS;

        $sections = collect(CustomerAppNavItem::DEFAULTS)->map(function (array $definition, string $key) use ($iconOptions) {
            return Section::make($definition['label'])
                ->columns(4)
                ->components([
                    TextInput::make("items.{$key}.label")
                        ->label('عنوان نمایشی')
                        ->required()
                        ->maxLength(50),
                    Select::make("items.{$key}.icon")
                        ->label('آیکون')
                        ->options($iconOptions)
                        ->required(),
                    TextInput::make("items.{$key}.sort_order")
                        ->label('ترتیب نمایش')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(99),
                    Toggle::make("items.{$key}.is_visible")
                        ->label('نمایش داده شود')
                        ->inline(false),
                ]);
        })->values()->all();

        return $schema
            ->statePath('data')
            ->components($sections);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state['items'] as $key => $values) {
            CustomerAppNavItem::query()->where('key', $key)->update([
                'label' => $values['label'],
                'icon' => $values['icon'],
                'sort_order' => $values['sort_order'],
                'is_visible' => $values['is_visible'],
            ]);
        }

        Notification::make()->title('منوی پایین اپ مشتریان ذخیره شد.')->success()->send();
    }
}
