<?php

namespace App\Filament\Resources\ChannelAllotments;

use App\Filament\Resources\ChannelAllotments\Pages\ManageChannelAllotments;
use App\Filament\Support\FieldHelp;
use App\Models\ChannelAllotment;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChannelAllotmentResource extends Resource
{
    protected static ?string $model = ChannelAllotment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'سهمیه کانال';

    protected static ?string $pluralModelLabel = 'سهمیه‌بندی کانال‌ها';

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
                    ->hintAction(FieldHelp::make('channel-allotments.room_type_id')),
                Select::make('sales_channel_id')
                    ->label('کانال فروش')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->hintAction(FieldHelp::make('channel-allotments.sales_channel_id')),
                TextInput::make('allotment_count')
                    ->label('تعداد سهمیه')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->minValue(0)
                    ->helperText('حداکثر واحدِ هم‌زمان در یک شب. رزرو بیشتر از این برای این کانال ثبت نمی‌شود؛ صفر یعنی فروش بسته است.')
                    ->hintAction(FieldHelp::make('channel-allotments.allotment_count')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('roomType.name')->label('نوع اتاق/ویلا'),
                TextColumn::make('channel.name')->label('کانال فروش'),
                TextColumn::make('allotment_count')->label('تعداد سهمیه'),
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
            'index' => ManageChannelAllotments::route('/'),
        ];
    }
}
