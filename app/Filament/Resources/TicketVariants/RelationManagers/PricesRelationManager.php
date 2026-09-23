<?php

namespace App\Filament\Resources\TicketVariants\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * A new row here is how a price "change" happens - the old row's
 * effective_until is set to now, a new row starts from now. Nothing here
 * ever overwrites an existing row's amount, which is what keeps every
 * already-issued ticket's own frozen unit_price meaningful (section 19).
 */
class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $title = 'تاریخچه‌ی قیمت';

    protected static ?string $modelLabel = 'قیمت';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('مبلغ (ریال)')
                    ->numeric()
                    ->required(),
                DateTimePicker::make('effective_from')
                    ->label('اعتبار از')
                    ->default(now())
                    ->required(),
                DateTimePicker::make('effective_until')
                    ->label('اعتبار تا (خالی = تا اطلاع ثانوی)'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('amount')->label('مبلغ')->numeric(),
                TextColumn::make('effective_from')->label('از')->dateTime('Y-m-d H:i'),
                TextColumn::make('effective_until')->label('تا')->dateTime('Y-m-d H:i')->placeholder('تا اطلاع ثانوی'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['created_by'] = Auth::id();

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
