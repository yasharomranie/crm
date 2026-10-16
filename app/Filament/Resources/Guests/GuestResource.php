<?php

namespace App\Filament\Resources\Guests;

use App\Filament\Resources\Guests\Pages\CreateGuest;
use App\Filament\Resources\Guests\Pages\EditGuest;
use App\Filament\Resources\Guests\Pages\ListGuests;
use App\Filament\Resources\Guests\Pages\ViewGuest;
use App\Filament\Resources\Guests\Schemas\GuestInfolist;
use App\Filament\Support\FieldHelp;
use App\Filament\Support\TableExports;
use App\Models\Guest;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $modelLabel = 'مهمان';

    protected static ?string $pluralModelLabel = 'مهمانان';

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return GuestInfolist::configure($schema);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام و نام خانوادگی')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('guests.name')),
                TextInput::make('national_code')
                    ->label('کد ملی')
                    ->unique(ignoreRecord: true)
                    ->maxLength(20)
                    ->hintAction(FieldHelp::make('guests.national_code')),
                TextInput::make('mobile')
                    ->label('شماره موبایل')
                    ->tel()
                    ->hintAction(FieldHelp::make('guests.mobile')),
                TextInput::make('email')
                    ->label('ایمیل')
                    ->email()
                    ->hintAction(FieldHelp::make('guests.email')),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('guests.notes')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('mobile')->label('موبایل')->placeholder('—'),
                TextColumn::make('national_code')->label('کد ملی')->placeholder('—'),
                TextColumn::make('loyalty_tier')
                    ->label('سطح وفاداری')
                    ->state(fn (Guest $record) => $record->loyaltyTier())
                    ->badge(),
                TextColumn::make('wallet_balance')->label('کیف پول')->numeric()->suffix(' تومان'),
                TextColumn::make('bookings_count')->label('تعداد رزرو')->counts('bookings')->numeric(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                TableExports::excel('مهمانان', static::exportColumns()),
                TableExports::pdf('گزارش مهمانان', static::exportColumns()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, \Closure(Guest): mixed>
     */
    private static function exportColumns(): array
    {
        return [
            'نام' => fn (Guest $guest) => $guest->name,
            'موبایل' => fn (Guest $guest) => $guest->mobile,
            'کد ملی' => fn (Guest $guest) => $guest->national_code,
            'سطح وفاداری' => fn (Guest $guest) => $guest->loyaltyTier()->getLabel(),
            'کیف پول (تومان)' => fn (Guest $guest) => Number::toPersian(number_format($guest->wallet_balance)),
            'تعداد رزرو' => fn (Guest $guest) => Number::toPersian($guest->bookings()->count()),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
            RelationManagers\DealsRelationManager::class,
            RelationManagers\TicketsRelationManager::class,
            RelationManagers\FeedbacksRelationManager::class,
            RelationManagers\WalletTransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
            'create' => CreateGuest::route('/create'),
            'view' => ViewGuest::route('/{record}'),
            'edit' => EditGuest::route('/{record}/edit'),
        ];
    }
}
