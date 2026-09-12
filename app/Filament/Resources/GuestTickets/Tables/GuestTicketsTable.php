<?php

namespace App\Filament\Resources\GuestTickets\Tables;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\GuestTicket;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GuestTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guest.name')->label('مشتری')->searchable(),
                TextColumn::make('subject')->label('موضوع')->searchable(),
                TextColumn::make('priority')->label('فوریت')->badge(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('sla_due_at')->label('مهلت پاسخ')->dateTime('Y/m/d H:i')->placeholder('—'),
                IconColumn::make('overdue')
                    ->label('معوق؟')
                    ->boolean()
                    ->state(fn (GuestTicket $record) => $record->isOverdue()),
            ])
            ->defaultSort('sla_due_at')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(TicketStatus::class),
                SelectFilter::make('priority')->label('فوریت')->options(TicketPriority::class),
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
}
