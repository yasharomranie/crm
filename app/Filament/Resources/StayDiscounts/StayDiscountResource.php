<?php

namespace App\Filament\Resources\StayDiscounts;

use App\Filament\Resources\StayDiscounts\Pages\ManageStayDiscounts;
use App\Models\StayDiscount;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StayDiscountResource extends Resource
{
    protected static ?string $model = StayDiscount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'تخفیف اقامت طولانی';

    protected static ?string $pluralModelLabel = 'تخفیف اقامت طولانی';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_type_id')
                    ->label('نوع اتاق/ویلا')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('همه‌ی انواع')
                    ->helperText('خالی = برای همه‌ی انواع اتاق.'),
                TextInput::make('min_nights')
                    ->label('از چند شب به بالا')
                    ->numeric()->minValue(2)->maxValue(365)->required(),
                TextInput::make('percent')
                    ->label('درصد تخفیف روی شب‌های اتاق')
                    ->numeric()->minValue(0.01)->maxValue(100)->required()
                    ->helperText('اگر چند پله تعریف کنید، برای هر اقامت بهترین پله‌ای که شرطش را دارد اعمال می‌شود؛ پله‌ها جمع نمی‌شوند.'),
                Toggle::make('is_active')->label('فعال')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roomType.name')->label('نوع اتاق/ویلا')->placeholder('همه‌ی انواع'),
                TextColumn::make('min_nights')->label('از شب')->numeric(),
                TextColumn::make('percent')->label('درصد تخفیف')->suffix('٪'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->defaultSort('min_nights')
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
            'index' => ManageStayDiscounts::route('/'),
        ];
    }
}
