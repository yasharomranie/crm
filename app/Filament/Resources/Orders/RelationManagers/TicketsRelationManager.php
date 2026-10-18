<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Domain\Ticketing\RefundService;
use App\Domain\Ticketing\TicketAdministration;
use App\Domain\Ticketing\TicketingException;
use App\Enums\AdmissionTicketStatus;
use App\Models\AdmissionTicket;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The tickets of one order, with the things staff do to a sold ticket:
 * look at it (usage, validity), reprint it, refund it, or block it at the
 * gates - and lift that block again.
 */
class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    protected static ?string $title = 'بلیت‌ها';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ticket_number')
            ->columns([
                TextColumn::make('ticket_number')->label('شماره')->searchable(),
                TextColumn::make('ticketVariant.name')->label('نوع'),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('uses')->label('مصرف')->state(fn (AdmissionTicket $record) => "{$record->used_uses} از {$record->allowed_uses}"),
                TextColumn::make('valid_until')->label('اعتبار تا')->jalaliDateTime('Y/m/d H:i'),
                TextColumn::make('blocked_reason')->label('دلیل مسدودی')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('print')
                    ->label('چاپ')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (AdmissionTicket $record) => route('admission-tickets.print', $record))
                    ->openUrlInNewTab(),
                Action::make('refund')
                    ->label('استرداد')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->authorize(fn () => (bool) auth()->user()?->can('orders.update'))
                    ->visible(fn (AdmissionTicket $record) => $record->status === AdmissionTicketStatus::Active)
                    ->schema([Textarea::make('reason')->label('دلیل استرداد')->required()->maxLength(500)])
                    ->requiresConfirmation()
                    ->action(function (AdmissionTicket $record, array $data): void {
                        try {
                            app(RefundService::class)->refundTicket($record, $data['reason'], null, auth()->user());
                        } catch (TicketingException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('بلیت مسترد شد')->success()->send();
                    }),
                Action::make('block')
                    ->label('مسدود کردن')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('warning')
                    ->authorize(fn () => (bool) auth()->user()?->can('orders.update'))
                    ->visible(fn (AdmissionTicket $record) => in_array($record->status, [AdmissionTicketStatus::Active, AdmissionTicketStatus::PartiallyUsed], true))
                    ->schema([Textarea::make('reason')->label('دلیل مسدودی')->required()->maxLength(255)])
                    ->action(function (AdmissionTicket $record, array $data): void {
                        app(TicketAdministration::class)->block($record, $data['reason']);

                        Notification::make()->title('بلیت مسدود شد؛ در گیت‌ها پذیرفته نمی‌شود')->success()->send();
                    }),
                Action::make('unblock')
                    ->label('رفع مسدودی')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->authorize(fn () => (bool) auth()->user()?->can('orders.update'))
                    ->visible(fn (AdmissionTicket $record) => $record->status === AdmissionTicketStatus::Blocked)
                    ->requiresConfirmation()
                    ->action(function (AdmissionTicket $record): void {
                        app(TicketAdministration::class)->unblock($record);

                        Notification::make()->title('مسدودی برداشته شد')->success()->send();
                    }),
            ]);
    }
}
