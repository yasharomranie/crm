<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Filament\Resources\Bookings\Actions\AddChargeAction;
use App\Models\BookingCharge;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $title = 'اقلام صورتحساب (فولیو)';

    protected static ?string $modelLabel = 'قلم صورتحساب';

    protected static ?string $pluralModelLabel = 'اقلام صورتحساب';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('شرح')
                    ->helperText('مثلاً «مینی‌بار» یا «رستوران»')
                    ->required()
                    ->maxLength(255),
                TextInput::make('quantity')
                    ->label('تعداد')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('unit_amount')
                    ->label('مبلغ واحد (تومان)')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->label('شرح'),
                TextColumn::make('quantity')->label('تعداد')->numeric(),
                TextColumn::make('unit_amount')->label('مبلغ واحد')->numeric()->suffix(' تومان'),
                TextColumn::make('id')
                    ->label('جمع')
                    ->state(fn (BookingCharge $record) => Number::toPersian(number_format($record->lineTotal())))
                    ->suffix(' تومان'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AddChargeAction::make()->record(fn () => $this->getOwnerRecord()),
                CreateAction::make(),
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
