<?php

namespace App\Filament\Resources\BookingPromoCodes;

use App\Enums\PriceAdjustmentType;
use App\Filament\Resources\BookingPromoCodes\Pages\ManageBookingPromoCodes;
use App\Models\BookingPromoCode;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BookingPromoCodeResource extends Resource
{
    protected static ?string $model = BookingPromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'کد تخفیف رزرو';

    protected static ?string $pluralModelLabel = 'کدهای تخفیف رزرو';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('کد')
                    ->required()
                    ->maxLength(40)
                    ->unique(ignoreRecord: true)
                    ->dehydrateStateUsing(fn (string $state) => BookingPromoCode::normalize($state))
                    ->helperText('مثلاً NOWRUZ1405؛ حروف بزرگ و کوچک فرقی ندارند.'),
                TextInput::make('name')->label('عنوان (برای خودتان)')->maxLength(255),
                Select::make('discount_type')
                    ->label('نوع تخفیف')
                    ->options(PriceAdjustmentType::class)
                    ->default(PriceAdjustmentType::Percentage)
                    ->required(),
                TextInput::make('discount_value')
                    ->label('مقدار تخفیف')
                    ->numeric()->minValue(0.01)->required()
                    ->helperText('درصد یا مبلغ به تومان (بسته به نوع). تخفیف روی شب‌های اتاق حساب می‌شود، نه نفر اضافه.'),
                DatePicker::make('valid_from')->jalali()->displayFormat('Y/m/d')->label('معتبر از'),
                DatePicker::make('valid_until')->jalali()->displayFormat('Y/m/d')->label('معتبر تا')->afterOrEqual('valid_from'),
                TextInput::make('min_nights')->label('حداقل شب اقامت')->numeric()->minValue(1),
                TextInput::make('usage_limit')->label('حداکثر دفعات استفاده')->numeric()->minValue(1)->helperText('خالی = نامحدود.'),
                Toggle::make('is_active')->label('فعال')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('کد')->searchable(),
                TextColumn::make('name')->label('عنوان')->placeholder('—'),
                TextColumn::make('discount_type')->label('نوع'),
                TextColumn::make('discount_value')->label('مقدار'),
                TextColumn::make('used_count')->label('دفعات استفاده')->numeric(),
                TextColumn::make('valid_until')->label('تا تاریخ')->jalaliDate('Y/m/d')->placeholder('—'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBookingPromoCodes::route('/'),
        ];
    }
}
