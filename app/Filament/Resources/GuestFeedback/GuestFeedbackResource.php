<?php

namespace App\Filament\Resources\GuestFeedback;

use App\Filament\Resources\GuestFeedback\Pages\CreateGuestFeedback;
use App\Filament\Resources\GuestFeedback\Pages\EditGuestFeedback;
use App\Filament\Resources\GuestFeedback\Pages\ListGuestFeedback;
use App\Filament\Resources\GuestFeedback\Schemas\GuestFeedbackForm;
use App\Filament\Resources\GuestFeedback\Tables\GuestFeedbackTable;
use App\Models\GuestFeedback;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GuestFeedbackResource extends Resource
{
    protected static ?string $model = GuestFeedback::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFaceSmile;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $modelLabel = 'نظرسنجی';

    protected static ?string $pluralModelLabel = 'بازخوردها و NPS';

    public static function form(Schema $schema): Schema
    {
        return GuestFeedbackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GuestFeedbackTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuestFeedback::route('/'),
            'create' => CreateGuestFeedback::route('/create'),
            'edit' => EditGuestFeedback::route('/{record}/edit'),
        ];
    }
}
