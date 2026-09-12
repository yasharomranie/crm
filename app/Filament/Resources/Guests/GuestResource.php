<?php

namespace App\Filament\Resources\Guests;

use App\Filament\Resources\Guests\Pages\ManageGuests;
use App\Models\Guest;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'مهمان';

    protected static ?string $pluralModelLabel = 'مهمانان';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام و نام خانوادگی')
                    ->required()
                    ->maxLength(255),
                TextInput::make('national_code')
                    ->label('کد ملی')
                    ->unique(ignoreRecord: true)
                    ->maxLength(20),
                TextInput::make('mobile')
                    ->label('شماره موبایل')
                    ->tel(),
                TextInput::make('email')
                    ->label('ایمیل')
                    ->email(),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('mobile')->label('موبایل')->placeholder('—'),
                TextColumn::make('national_code')->label('کد ملی')->placeholder('—'),
                TextColumn::make('bookings_count')->label('تعداد رزرو')->counts('bookings'),
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
            'index' => ManageGuests::route('/'),
        ];
    }
}
