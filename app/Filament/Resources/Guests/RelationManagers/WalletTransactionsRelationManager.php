<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Enums\WalletTransactionType;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->mutateFormDataUsing(function (array $data) {
                        $data['created_by'] = auth()->id();

                        return $data;
                    })
                    ->after(function ($record) {
                        $record->guest->increment(
                            'wallet_balance',
                            $record->type === WalletTransactionType::Credit ? $record->amount : -$record->amount
                        );
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
