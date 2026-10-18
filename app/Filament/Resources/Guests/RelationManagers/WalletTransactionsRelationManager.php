<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Guest;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    protected static ?string $title = 'گردش کیف پول';

    protected static ?string $modelLabel = 'تراکنش';

    protected static ?string $pluralModelLabel = 'تراکنش‌ها';

    /**
     * Most rows here are system-generated (cashback on checkout). Manual
     * entries (e.g. a goodwill credit for a resolved complaint) are still
     * allowed, but transactions are never edited/deleted - a wallet ledger
     * should stay an append-only history.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('نوع')
                    ->options(WalletTransactionType::class)
                    ->required(),
                TextInput::make('amount')
                    ->label('مبلغ (تومان)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
                TextInput::make('reason')
                    ->label('بابت')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('تاریخ')->jalaliDateTime('Y/m/d H:i'),
                TextColumn::make('type')->label('نوع')->badge(),
                TextColumn::make('amount')->label('مبلغ')->numeric()->suffix(' تومان'),
                TextColumn::make('reason')->label('بابت'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, CreateAction $action, WalletTransactionsRelationManager $livewire) {
                        /** @var Guest $guest */
                        $guest = $livewire->getOwnerRecord();
                        $amount = (int) $data['amount'];

                        try {
                            return $data['type'] === WalletTransactionType::Credit->value
                                ? $guest->creditWallet($amount, $data['reason'])
                                : $guest->debitWallet($amount, $data['reason']);
                        } catch (InsufficientWalletBalanceException $exception) {
                            Notification::make()
                                ->danger()
                                ->title($exception->getMessage())
                                ->body('موجودی فعلی: '.Number::toPersian(number_format($exception->balance)).' تومان')
                                ->send();

                            $action->halt();
                        }
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
