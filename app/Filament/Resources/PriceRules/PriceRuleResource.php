<?php

namespace App\Filament\Resources\PriceRules;

use App\Enums\PriceAdjustmentType;
use App\Filament\Resources\PriceRules\Pages\ManagePriceRules;
use App\Filament\Support\FieldHelp;
use App\Models\PriceRule;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PriceRuleResource extends Resource
{
    protected static ?string $model = PriceRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'قانون قیمت‌گذاری';

    protected static ?string $pluralModelLabel = 'قیمت‌گذاری پویا';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_type_id')
                    ->label('نوع اتاق/ویلا')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->hintAction(FieldHelp::make('price-rules.room_type_id')),
                TextInput::make('name')
                    ->label('عنوان قانون')
                    ->helperText('مثلاً «تعطیلات نوروز» یا «آخر هفته»')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('price-rules.name')),
                DatePicker::make('start_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('از تاریخ')
                    ->required()
                    ->hintAction(FieldHelp::make('price-rules.start_date')),
                DatePicker::make('end_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تا تاریخ')
                    ->required()
                    ->afterOrEqual('start_date')
                    ->hintAction(FieldHelp::make('price-rules.end_date')),
                Select::make('adjustment_type')
                    ->label('نوع تغییر قیمت')
                    ->options(PriceAdjustmentType::class)
                    ->default(PriceAdjustmentType::Percentage)
                    ->required()
                    ->live()
                    ->hintAction(FieldHelp::make('price-rules.adjustment_type')),
                TextInput::make('adjustment_value')
                    ->label('مقدار تغییر')
                    ->numeric()
                    ->required()
                    ->helperText(fn (Get $get) => $get('adjustment_type') === PriceAdjustmentType::Fixed
                        ? 'مبلغ به تومان به نرخ پایه اضافه می‌شود (برای کاهش، عدد منفی وارد کنید).'
                        : 'درصدی که به نرخ پایه اضافه می‌شود (برای تخفیف، عدد منفی وارد کنید).')
                    ->hintAction(FieldHelp::make('price-rules.adjustment_value')),
                TextInput::make('priority')
                    ->label('اولویت')
                    ->numeric()
                    ->default(0)
                    ->helperText('در صورت هم‌پوشانی چند قانون روی یک تاریخ، قانون با اولویت بالاتر اعمال می‌شود.')
                    ->hintAction(FieldHelp::make('price-rules.priority')),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->hintAction(FieldHelp::make('price-rules.is_active')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roomType.name')->label('نوع اتاق/ویلا'),
                TextColumn::make('name')->label('عنوان قانون')->searchable(),
                TextColumn::make('start_date')->label('از تاریخ')->jalaliDate('Y/m/d'),
                TextColumn::make('end_date')->label('تا تاریخ')->jalaliDate('Y/m/d'),
                TextColumn::make('adjustment_type')->label('نوع'),
                TextColumn::make('adjustment_value')->label('مقدار'),
                TextColumn::make('priority')->label('اولویت'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                //
            ])
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
            'index' => ManagePriceRules::route('/'),
        ];
    }
}
