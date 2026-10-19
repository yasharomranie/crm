<?php

namespace App\Filament\Resources\CancellationPolicies;

use App\Filament\Resources\CancellationPolicies\Pages\ManageCancellationPolicies;
use App\Models\CancellationPolicy;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CancellationPolicyResource extends Resource
{
    protected static ?string $model = CancellationPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'سیاست لغو';

    protected static ?string $pluralModelLabel = 'سیاست‌های لغو';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام سیاست')
                    ->helperText('مثلاً «استاندارد» یا «نوروز - غیرقابل بازگشت»')
                    ->required()
                    ->maxLength(255),
                Repeater::make('rules')
                    ->label('پله‌های لغو')
                    ->relationship('rules')
                    ->helperText('هر پله می‌گوید: اگر دست‌کم این‌قدر روز مانده به ورود لغو شود، این درصد از کل صورت‌حساب جریمه می‌شود. مثلاً «۷ روز ← ۰٪»، «۳ روز ← ۳۰٪»، «۱ روز ← ۷۰٪».')
                    ->schema([
                        TextInput::make('min_days_before')
                            ->label('دست‌کم چند روز مانده به ورود')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(365)
                            ->required(),
                        TextInput::make('penalty_percent')
                            ->label('درصد جریمه')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->columnSpanFull(),
                TextInput::make('late_penalty_percent')
                    ->label('جریمه‌ی لغو دیرتر از همه‌ی پله‌ها (٪)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(100)
                    ->required(),
                TextInput::make('no_show_penalty_percent')
                    ->label('جریمه‌ی عدم حضور (٪)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(100)
                    ->required(),
                Toggle::make('is_default')
                    ->label('سیاست پیش‌فرض رزروهای تازه')
                    ->helperText('فقط یک سیاست پیش‌فرض است؛ با روشن کردن این، پیش‌فرض قبلی برداشته می‌شود.'),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                IconColumn::make('is_default')->label('پیش‌فرض')->boolean(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('terms')
                    ->label('شرایط')
                    ->state(fn (CancellationPolicy $record) => CancellationPolicy::describe($record->terms()))
                    ->wrap(),
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
            'index' => ManageCancellationPolicies::route('/'),
        ];
    }
}
