<?php

namespace App\Filament\Resources\RoomTypes;

use App\Filament\Resources\RoomTypes\Pages\ManageRoomTypes;
use App\Filament\Support\FieldHelp;
use App\Models\RoomType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomTypeResource extends Resource
{
    protected static ?string $model = RoomType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'نوع اتاق/ویلا';

    protected static ?string $pluralModelLabel = 'انواع اتاق/ویلا';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام نوع اتاق')
                    ->helperText('مثلاً «ویلای دو نفره» یا «سوئیت خانوادگی»')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('room-types.name')),
                TextInput::make('capacity')
                    ->label('ظرفیت نفرات')
                    ->numeric()
                    ->default(2)
                    ->required()
                    ->hintAction(FieldHelp::make('room-types.capacity')),
                TextInput::make('base_price')
                    ->label('نرخ پایه هر شب (تومان)')
                    ->numeric()
                    ->required()
                    ->hintAction(FieldHelp::make('room-types.base_price')),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('room-types.description')),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->hintAction(FieldHelp::make('room-types.is_active')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('capacity')->label('ظرفیت'),
                TextColumn::make('base_price')->label('نرخ پایه هر شب')->numeric()->suffix(' تومان'),
                TextColumn::make('rooms_count')->label('تعداد واحد')->counts('rooms'),
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
            'index' => ManageRoomTypes::route('/'),
        ];
    }
}
