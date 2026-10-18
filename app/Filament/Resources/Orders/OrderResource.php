<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Ticketing\TicketAdministration;
use App\Domain\Ticketing\TicketingException;
use App\Enums\TicketSourceType;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Orders\RelationManagers\TicketsRelationManager;
use App\Models\Order;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where a sale is looked up after the fact: by order number, buyer name or
 * phone, or the number printed on any of its tickets. Orders are never
 * edited or deleted here (they are financial records); what staff can do
 * is refund the unused tickets, and block/unblock individual tickets.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'گیشه بلیط';

    protected static ?string $modelLabel = 'سفارش بلیت';

    protected static ?string $pluralModelLabel = 'سفارش‌ها و بلیت‌ها';

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('اطلاعات سفارش')->columns(3)->schema([
                TextEntry::make('order_number')->label('شماره‌ی سفارش'),
                TextEntry::make('source_type')->label('منبع')->badge(),
                TextEntry::make('created_at')->label('زمان')->state(fn (Order $record) => $record->created_at->toJalali('Y/m/d H:i')),
                TextEntry::make('customer_name')->label('خریدار')->placeholder('—'),
                TextEntry::make('customer_phone')->label('تلفن')->placeholder('—'),
                TextEntry::make('creator.name')->label('فروشنده')->placeholder('—'),
                TextEntry::make('original_amount')->label('مبلغ اولیه')->numeric()->suffix(' تومان'),
                TextEntry::make('discount_amount')->label('تخفیف')->numeric()->suffix(' تومان'),
                TextEntry::make('discount_code_snapshot')->label('کد تخفیف')->placeholder('—'),
                TextEntry::make('final_amount')->label('مبلغ نهایی')->numeric()->suffix(' تومان'),
                TextEntry::make('amount_paid')->label('پرداخت‌شده')->numeric()->suffix(' تومان'),
                TextEntry::make('refunded')->label('مسترد‌شده')->state(fn (Order $record) => number_format((int) $record->refunds()->sum('amount')).' تومان'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('order_number')->label('سفارش')->searchable(),
                TextColumn::make('customer_name')->label('خریدار')->searchable()->placeholder('—'),
                TextColumn::make('customer_phone')->label('تلفن')->searchable()->placeholder('—'),
                TextColumn::make('ticket_numbers')
                    ->label('بلیت‌ها')
                    ->state(fn (Order $record) => $record->tickets->pluck('ticket_number')->take(2)->implode('، ').($record->tickets->count() > 2 ? ' …' : ''))
                    ->searchable(query: fn (Builder $query, string $search) => $query->orWhereHas('tickets', fn (Builder $tickets) => $tickets->where('admission_tickets.ticket_number', 'like', "%{$search}%"))),
                TextColumn::make('source_type')->label('منبع')->badge(),
                TextColumn::make('final_amount')->label('مبلغ نهایی')->numeric()->suffix(' تومان')->sortable(),
                TextColumn::make('created_at')->label('زمان')->jalaliDateTime('Y/m/d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('source_type')->label('منبع')->options(TicketSourceType::class),
                Filter::make('created')
                    ->label('بازه‌ی زمانی')
                    ->schema([
                        DatePicker::make('from')->label('از')->jalali(),
                        DatePicker::make('until')->label('تا')->jalali(),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $q, $until) => $q->whereDate('created_at', '<=', $until))),
                Filter::make('refunded')->label('دارای استرداد')->query(fn (Builder $query) => $query->has('refunds'))->toggle(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TicketsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    public static function refundUnusedAction(): Action
    {
        return Action::make('refundUnused')
            ->label('استرداد بلیت‌های استفاده‌نشده')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->authorize('update')
            ->requiresConfirmation()
            ->modalDescription('همه‌ی بلیت‌های فعالِ این سفارش که هنوز اسکن نشده‌اند مسترد می‌شوند. بلیت‌های استفاده‌شده، مسدود یا قبلاً مسترد‌شده دست‌نخورده می‌مانند.')
            ->schema([Textarea::make('reason')->label('دلیل استرداد')->required()->maxLength(500)])
            ->action(function (Order $record, array $data): void {
                try {
                    $count = app(TicketAdministration::class)->refundUnused($record, $data['reason'], auth()->user());
                } catch (TicketingException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($count > 0 ? "{$count} بلیت مسترد شد" : 'بلیت قابل استردادی در این سفارش نبود')
                    ->{$count > 0 ? 'success' : 'warning'}()
                    ->send();
            });
    }
}
