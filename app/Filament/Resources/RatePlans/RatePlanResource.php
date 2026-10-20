<?php

namespace App\Filament\Resources\RatePlans;

use App\Enums\PriceAdjustmentType;
use App\Filament\Resources\RatePlans\Pages\ManageRatePlans;
use App\Models\RatePlan;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RatePlanResource extends Resource
{
    protected static ?string $model = RatePlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'نرخ فروش';

    protected static ?string $pluralModelLabel = 'نرخ‌های فروش (با صبحانه، غیرقابل بازگشت...)';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('نام')->helperText('مثلاً «با صبحانه»، «بدون صبحانه» یا «غیرقابل بازگشت»')->required()->maxLength(255),
                Select::make('adjustment_type')
                    ->label('نوع تغییر قیمت')
                    ->options(PriceAdjustmentType::class)
                    ->default(PriceAdjustmentType::Percentage)
                    ->required()
                    ->live(),
                TextInput::make('adjustment_value')
                    ->label('مقدار تغییر')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->helperText(fn (Get $get) => $get('adjustment_type') === PriceAdjustmentType::Fixed
                        ? 'مبلغ به تومان که به نرخ هر شب اضافه می‌شود (برای کاهش، عدد منفی).'
                        : 'درصدی که به نرخ هر شب اضافه می‌شود (برای تخفیف، عدد منفی؛ مثلاً -10 برای غیرقابل بازگشت).'),
                Select::make('cancellation_policy_id')
                    ->label('سیاست لغو مخصوص این نرخ')
                    ->relationship('cancellationPolicy', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('سیاست پیش‌فرض')
                    ->helperText('مثلاً برای «غیرقابل بازگشت» سیاستی با جریمه‌ی ۱۰۰٪ بسازید و اینجا انتخاب کنید.'),
                Textarea::make('description')->label('توضیح (برای پذیرش)')->columnSpanFull(),
                TextInput::make('sort_order')->label('ترتیب نمایش')->numeric()->minValue(0)->default(0),
                Toggle::make('is_active')->label('فعال')->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('adjustment_type')->label('نوع'),
                TextColumn::make('adjustment_value')->label('مقدار'),
                TextColumn::make('cancellationPolicy.name')->label('سیاست لغو')->placeholder('پیش‌فرض'),
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
            'index' => ManageRatePlans::route('/'),
        ];
    }
}
