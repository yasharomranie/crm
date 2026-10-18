<?php

namespace App\Filament\Pages;

use App\Domain\Crm\WalletExpiryService;
use App\Enums\WalletTransactionType;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Filament\Support\TableExports;
use App\Models\Guest;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/**
 * What the business owes its customers in wallet credit, and whether the
 * ledger agrees with each cached balance. Money sitting in wallets is a
 * liability: this page is where it is counted, where credit about to
 * expire shows up, and where a balance that no longer matches its ledger
 * (changed outside Guest::creditWallet()/debitWallet()) is caught.
 */
class WalletLiabilityReport extends Page implements HasTable
{
    use HasAccessControlledPage;
    use InteractsWithTable;

    protected string $view = 'filament.pages.wallet-liability-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $navigationLabel = 'گزارش بدهی کیف پول';

    protected static ?string $title = 'گزارش بدهی کیف پول';

    private const LEDGER_SUM_SQL = "COALESCE((select sum(case when type = 'credit' then amount else -amount end) from wallet_transactions where wallet_transactions.guest_id = guests.id), 0)";

    public static function accessControlPermission(): string
    {
        return 'wallet-liability-report.access';
    }

    /**
     * @return array{total_liability: int, guests_with_balance: int, credited_30d: int, debited_30d: int, expiring_30d: int, mismatched: int}
     */
    public function summary(): array
    {
        $since = now()->subDays(30);

        $expiring = 0;
        $expiryService = app(WalletExpiryService::class);

        Guest::query()
            ->whereIn('id', WalletTransaction::query()
                ->where('type', WalletTransactionType::Credit)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays(30)])
                ->whereNull('expiry_processed_at')
                ->select('guest_id'))
            ->get()
            ->each(function (Guest $guest) use ($expiryService, &$expiring) {
                $expiring += (int) $expiryService->unspentCredits($guest)
                    ->filter(fn (array $lot) => $lot['expires_at'] !== null && $lot['expires_at']->between(now(), now()->addDays(30)))
                    ->sum('remaining');
            });

        return [
            'total_liability' => (int) Guest::query()->where('wallet_balance', '>', 0)->sum('wallet_balance'),
            'guests_with_balance' => Guest::query()->where('wallet_balance', '>', 0)->count(),
            'credited_30d' => (int) WalletTransaction::query()->where('type', WalletTransactionType::Credit)->where('created_at', '>=', $since)->sum('amount'),
            'debited_30d' => (int) WalletTransaction::query()->where('type', WalletTransactionType::Debit)->where('created_at', '>=', $since)->sum('amount'),
            'expiring_30d' => $expiring,
            'mismatched' => Guest::query()->whereRaw('wallet_balance != '.self::LEDGER_SUM_SQL)->count(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Guest::query()
                    ->where(fn (Builder $query) => $query
                        ->where('wallet_balance', '!=', 0)
                        ->orWhereHas('walletTransactions'))
                    ->selectRaw('guests.*, '.self::LEDGER_SUM_SQL.' as ledger_balance')
            )
            ->defaultSort('wallet_balance', 'desc')
            ->columns([
                TextColumn::make('name')->label('مشتری')->searchable(),
                TextColumn::make('mobile')->label('موبایل')->placeholder('—'),
                TextColumn::make('wallet_balance')->label('موجودی')->numeric()->suffix(' تومان')->sortable(),
                TextColumn::make('ledger_balance')
                    ->label('جمع گردش')
                    ->numeric()
                    ->suffix(' تومان')
                    ->color(fn (Guest $record) => (int) $record->ledger_balance !== (int) $record->wallet_balance ? 'danger' : null),
            ])
            ->filters([
                Filter::make('has_balance')
                    ->label('فقط دارای موجودی')
                    ->query(fn (Builder $query) => $query->where('wallet_balance', '>', 0)),
                Filter::make('mismatch')
                    ->label('ناهماهنگ با گردش')
                    ->query(fn (Builder $query) => $query->whereRaw('guests.wallet_balance != '.self::LEDGER_SUM_SQL)),
            ])
            ->headerActions([
                TableExports::excel('بدهی کیف پول', [
                    'مشتری' => fn (Guest $guest) => $guest->name,
                    'موبایل' => fn (Guest $guest) => $guest->mobile,
                    'موجودی (تومان)' => fn (Guest $guest) => (int) $guest->wallet_balance,
                    'جمع گردش (تومان)' => fn (Guest $guest) => (int) $guest->ledger_balance,
                ]),
            ]);
    }

    public function toman(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
