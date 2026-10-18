<?php

namespace App\Filament\Resources\GuestTickets\Tables;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Support\TableExports;
use App\Models\GuestTicket;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('—'),
                TextColumn::make('sla_due_at')->label('مهلت پاسخ')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
                TextColumn::make('csat_score')
                    ->label('رضایت (۱ تا ۵)')
                    ->badge()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 4 => 'success',
                        $state === 3 => 'warning',
                        default => 'danger',
                    })
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('csat_comment')->label('نظر مشتری')->limit(40)->tooltip(fn (GuestTicket $record) => $record->csat_comment)->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('overdue')
                    ->label('معوق؟')
                    ->boolean()
                    ->state(fn (GuestTicket $record) => $record->isOverdue()),
            ])
            ->defaultSort('sla_due_at')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(TicketStatus::class),
                SelectFilter::make('priority')->label('فوریت')->options(TicketPriority::class),
                Filter::make('unhappy')
                    ->label('مشتری ناراضی (امتیاز ۱ یا ۲)')
                    ->query(fn (Builder $query) => $query->whereBetween('csat_score', [1, 2]))
                    ->toggle(),
            ])
            ->headerActions([
                TableExports::excel('تیکت‌های-پشتیبانی', static::exportColumns()),
                TableExports::pdf('گزارش تیکت‌های پشتیبانی', static::exportColumns()),
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

    /**
     * @return array<string, \Closure(GuestTicket): mixed>
     */
    private static function exportColumns(): array
    {
        return [
            'مشتری' => fn (GuestTicket $ticket) => $ticket->guest?->name,
            'موضوع' => fn (GuestTicket $ticket) => $ticket->subject,
            'فوریت' => fn (GuestTicket $ticket) => $ticket->priority->getLabel(),
            'وضعیت' => fn (GuestTicket $ticket) => $ticket->status->getLabel(),
            'مهلت پاسخ' => fn (GuestTicket $ticket) => $ticket->sla_due_at?->toJalali('Y/m/d H:i'),
            'معوق؟' => fn (GuestTicket $ticket) => $ticket->isOverdue() ? 'بله' : 'خیر',
        ];
    }
}
