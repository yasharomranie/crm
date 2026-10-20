<?php

namespace App\Filament\Resources\ChargeItems;

use App\Filament\Resources\ChargeItems\Pages\ManageChargeItems;
use App\Models\ChargeItem;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChargeItemResource extends Resource
{
    protected static ?string $model = ChargeItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'خدمت یا هزینه‌ی جانبی';

    protected static ?string $pluralModelLabel = 'خدمات و هزینه‌های جانبی';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('نام')->helperText('مثلاً «صبحانه»، «شام»، «لباسشویی» یا «مینی‌بار - نوشابه»')->required()->maxLength(255),
                TextInput::make('unit_amount')->label('قیمت هر واحد (تومان)')->numeric()->minValue(0)->required(),
                TextInput::make('sort_order')->label('ترتیب نمایش')->numeric()->minValue(0)->default(0)->helperText('عدد کمتر بالاتر می‌آید.'),
                Toggle::make('is_active')->label('فعال')->default(true)->helperText('غیرفعال در فهرست افزودن به صورت‌حساب نمی‌آید؛ اقلام قبلاً نوشته‌شده تغییر نمی‌کنند.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('unit_amount')->label('قیمت هر واحد')->numeric()->suffix(' تومان'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->defaultSort('sort_order')
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
            'index' => ManageChargeItems::route('/'),
        ];
    }
}
