<?php

namespace App\Filament\Resources\GuestTickets;

use App\Filament\Resources\GuestTickets\Pages\CreateGuestTicket;
use App\Filament\Resources\GuestTickets\Pages\EditGuestTicket;
use App\Filament\Resources\GuestTickets\Pages\ListGuestTickets;
use App\Filament\Resources\GuestTickets\RelationManagers\RepliesRelationManager;
use App\Filament\Resources\GuestTickets\Schemas\GuestTicketForm;
use App\Filament\Resources\GuestTickets\Tables\GuestTicketsTable;
use App\Models\GuestTicket;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GuestTicketResource extends Resource
{
    protected static ?string $model = GuestTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $modelLabel = 'تیکت پشتیبانی';

    protected static ?string $pluralModelLabel = 'تیکت‌های پشتیبانی';

    protected static ?string $recordTitleAttribute = 'subject';

    public static function form(Schema $schema): Schema
    {
        return GuestTicketForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuestTicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RepliesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuestTickets::route('/'),
            'create' => CreateGuestTicket::route('/create'),
            'edit' => EditGuestTicket::route('/{record}/edit'),
        ];
    }
}
