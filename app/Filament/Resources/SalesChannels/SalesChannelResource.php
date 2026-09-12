<?php

namespace App\Filament\Resources\SalesChannels;

use App\Enums\SalesChannelType;
use App\Filament\Resources\SalesChannels\Pages\ManageSalesChannels;
use App\Models\SalesChannel;
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

class SalesChannelResource extends Resource
{
    protected static ?string $model = SalesChannel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'کانال فروش';

    protected static ?string $pluralModelLabel = 'کانال‌های فروش';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام کانال')
                    ->helperText('مثلاً «بوکینگ دات کام» یا «فروش حضوری»')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('نوع کانال')
                    ->options(SalesChannelType::class)
                    ->default(SalesChannelType::Ota)
                    ->required(),
                TextInput::make('commission_rate')
                    ->label('درصد کمیسیون')
                    ->numeric()
                    ->default(0)
                    ->suffix('%')
                    ->required()
                    ->helperText('کمیسیون این کانال هنگام تسویه‌حساب رزرو به‌صورت خودکار محاسبه می‌شود.'),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام کانال')->searchable(),
                TextColumn::make('type')->label('نوع'),
                TextColumn::make('commission_rate')->label('کمیسیون')->suffix('%'),
                TextColumn::make('bookings_count')->label('تعداد رزرو')->counts('bookings'),
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
            'index' => ManageSalesChannels::route('/'),
        ];
    }
}
