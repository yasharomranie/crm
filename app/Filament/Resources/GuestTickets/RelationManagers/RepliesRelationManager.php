<?php

namespace App\Filament\Resources\GuestTickets\RelationManagers;

use App\Models\GuestTicketReply;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "پاسخ‌ها" - the same reply thread a guest sees and posts to from
 * وب‌اپ مشتریان's "تیکت پشتیبانی" page (see
 * App\Http\Controllers\CustomerApp\SupportTicketController). A reply
 * created here is always from staff (guest_id stays null, user_id is the
 * signed-in admin) - a thread is append-only, never edited or deleted,
 * same reasoning as Guests\RelationManagers\WalletTransactionsRelationManager.
 */
class RepliesRelationManager extends RelationManager
{
    protected static string $relationship = 'replies';

    protected static ?string $title = 'پاسخ‌ها';

    protected static ?string $modelLabel = 'پاسخ';

    protected static ?string $pluralModelLabel = 'پاسخ‌ها';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('پیام')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('created_at')->label('تاریخ')->jalaliDateTime('Y/m/d H:i'),
                TextColumn::make('author')
                    ->label('نویسنده')
                    ->getStateUsing(fn (GuestTicketReply $record) => $record->authorName()),
                TextColumn::make('from')
                    ->label('نوع')
                    ->badge()
                    ->getStateUsing(fn (GuestTicketReply $record) => $record->isFromGuest() ? 'مشتری' : 'پشتیبانی')
                    ->color(fn (GuestTicketReply $record) => $record->isFromGuest() ? 'warning' : 'info'),
                TextColumn::make('body')->label('پیام')->wrap()->limit(200),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data) {
                        $data['user_id'] = auth()->id();

                        return $data;
                    }),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
