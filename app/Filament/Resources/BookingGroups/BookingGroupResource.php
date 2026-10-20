<?php

namespace App\Filament\Resources\BookingGroups;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Filament\Resources\BookingGroups\Actions\BookingGroupActions;
use App\Filament\Resources\BookingGroups\Pages\CreateBookingGroup;
use App\Filament\Resources\BookingGroups\Pages\ListBookingGroups;
use App\Filament\Resources\BookingGroups\Pages\ViewBookingGroup;
use App\Filament\Resources\BookingGroups\RelationManagers\BookingsRelationManager;
use App\Models\BookingGroup;
use App\Models\Guest;
use App\Models\Room;
use App\Models\SalesChannel;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class BookingGroupResource extends Resource
{
    protected static ?string $model = BookingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'رزرو گروهی';

    protected static ?string $pluralModelLabel = 'رزروهای گروهی';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guest_id')
                    ->label('مهمان مسئول گروه')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام و نام خانوادگی')->required(),
                        TextInput::make('mobile')->label('شماره موبایل')->tel(),
                    ])
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id),
                TextInput::make('name')->label('نام گروه (اختیاری)')->helperText('مثلاً «خانواده‌ی احمدی» یا «تور فرهنگی»')->maxLength(255),
                DatePicker::make('check_in_date')->jalali()->displayFormat('Y/m/d')->label('تاریخ ورود')->required()->default(now())->live(),
                DatePicker::make('check_out_date')->jalali()->displayFormat('Y/m/d')->label('تاریخ خروج')->required()->default(now()->addDay())->afterOrEqual('check_in_date')->live(),
                Select::make('status')
                    ->label('وضعیت رزروها')
                    ->options([BookingStatus::Pending->value => BookingStatus::Pending->getLabel(), BookingStatus::Confirmed->value => BookingStatus::Confirmed->getLabel()])
                    ->default(BookingStatus::Pending->value)
                    ->required(),
                Select::make('source')
                    ->label('منبع رزرو')
                    ->options(collect(BookingSource::cases())->mapWithKeys(fn (BookingSource $source) => [$source->value => $source->getLabel()])->all())
                    ->default(BookingSource::Phone->value)
                    ->required(),
                Select::make('sales_channel_id')
                    ->label('کانال فروش')
                    ->options(fn () => SalesChannel::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                Textarea::make('notes')->label('یادداشت')->columnSpanFull(),
                Repeater::make('rooms')
                    ->label('اتاق‌ها')
                    ->columnSpanFull()
                    ->minItems(1)
                    ->maxItems(20)
                    ->addActionLabel('افزودن اتاق')
                    ->default([['adults_count' => 2, 'children_count' => 0]])
                    ->columns(3)
                    ->schema([
                        Select::make('room_id')
                            ->label('اتاق/ویلا (فقط آزادها)')
                            ->options(function (Get $get) {
                                try {
                                    return Room::query()
                                        ->freeBetween(Carbon::parse($get('../../check_in_date')), Carbon::parse($get('../../check_out_date')))
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                } catch (\Throwable) {
                                    return [];
                                }
                            })
                            ->searchable()
                            ->required()
                            ->distinct(),
                        TextInput::make('adults_count')->label('بزرگسال')->numeric()->minValue(1)->default(2)->required(),
                        TextInput::make('children_count')->label('کودک')->numeric()->minValue(0)->default(0),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                TextEntry::make('code')->label('کد گروه')->fontFamily('mono'),
                TextEntry::make('name')->label('نام گروه')->placeholder('—'),
                TextEntry::make('guest.name')->label('مهمان مسئول'),
                TextEntry::make('rooms')->label('تعداد اتاق')->state(fn (BookingGroup $record) => Number::toPersian($record->bookings()->count())),
                TextEntry::make('total')->label('کل صورت‌حساب گروه')->state(fn (BookingGroup $record) => self::money($record->totalAmount())),
                TextEntry::make('paid')->label('پرداخت‌شده')->state(fn (BookingGroup $record) => self::money($record->paidAmount())),
                TextEntry::make('balance')->label('مانده‌ی گروه')->state(fn (BookingGroup $record) => self::money($record->balanceDue()))
                    ->color(fn (BookingGroup $record) => $record->balanceDue() > 0 ? 'warning' : 'success'),
                TextEntry::make('notes')->label('یادداشت')->placeholder('—')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['guest', 'bookings.charges', 'bookings.payments', 'bookings.room']))
            ->columns([
                TextColumn::make('code')->label('کد گروه')->fontFamily('mono')->searchable(),
                TextColumn::make('name')->label('نام گروه')->searchable()->placeholder('—'),
                TextColumn::make('guest.name')->label('مهمان مسئول')->searchable(),
                TextColumn::make('bookings_count')->label('اتاق')->counts('bookings')->numeric(),
                TextColumn::make('total')->label('صورت‌حساب')->state(fn (BookingGroup $record) => Number::toPersian(number_format($record->totalAmount())))->suffix(' تومان'),
                TextColumn::make('balance')->label('مانده')
                    ->state(fn (BookingGroup $record) => Number::toPersian(number_format($record->balanceDue())))
                    ->suffix(' تومان')
                    ->color(fn (BookingGroup $record) => $record->balanceDue() > 0 ? 'warning' : 'success'),
                TextColumn::make('created_at')->label('ثبت')->jalaliDate('Y/m/d'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                BookingGroupActions::pay(),
                BookingGroupActions::invoice(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BookingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingGroups::route('/'),
            'create' => CreateBookingGroup::route('/create'),
            'view' => ViewBookingGroup::route('/{record}'),
        ];
    }

    private static function money(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
