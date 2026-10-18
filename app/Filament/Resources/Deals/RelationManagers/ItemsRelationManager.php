<?php

namespace App\Filament\Resources\Deals\RelationManagers;

use App\Models\DealItem;
use App\Models\Product;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * The quote behind a deal: what is being sold, how many, at what price and
 * discount. The deal's value follows the lines (Deal::syncValueFromItems()),
 * so the pipeline's totals and the lead score see the real amount.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'اقلام و پیش‌فاکتور';

    protected static ?string $modelLabel = 'قلم';

    protected static ?string $pluralModelLabel = 'اقلام';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->label('شرح (چه چیزی فروخته می‌شود؟)')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('product_id')
                    ->label('محصول مرتبط (اختیاری)')
                    ->options(fn () => Product::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                TextInput::make('quantity')
                    ->label('تعداد')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                TextInput::make('unit_price')
                    ->label('قیمت واحد (تومان)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('discount_percent')
                    ->label('تخفیف (٪)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')->label('شرح')->wrap(),
                TextColumn::make('quantity')->label('تعداد')->numeric(),
                TextColumn::make('unit_price')->label('قیمت واحد')->numeric()->suffix(' تومان'),
                TextColumn::make('discount_percent')->label('تخفیف')->suffix('٪'),
                TextColumn::make('line_total')
                    ->label('جمع')
                    ->state(fn (DealItem $record) => Number::toPersian(number_format($record->lineTotal())).' تومان'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
