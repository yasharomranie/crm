<?php

namespace App\Filament\Resources\Gates\RelationManagers;

use App\Enums\TicketDirection;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * What this gate will let through - see App\Models\GateRule::matches()
 * and GateValidationService for how these rows are evaluated at scan
 * time. Nothing about "outbound", "return", or "round trip" is
 * hard-coded anywhere in that evaluation - it all comes from the rows an
 * admin adds here.
 */
class RulesRelationManager extends RelationManager
{
    protected static string $relationship = 'rules';

    protected static ?string $title = 'قوانین گیت';

    protected static ?string $modelLabel = 'قانون';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('محصول (خالی = هر محصول)')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('ticket_variant_id')
                    ->label('نوع بلیت (خالی = هر نوع)')
                    ->relationship('ticketVariant', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('direction')
                    ->label('جهت (خالی = بدون محدودیت)')
                    ->options(TicketDirection::class),
                TextInput::make('usage_stage')
                    ->label('مرحله‌ی مصرف (خالی = هر مرحله؛ ۱=اولین اسکن، ۲=دومین اسکن)')
                    ->numeric()
                    ->minValue(1),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('محصول')->placeholder('هر محصول'),
                TextColumn::make('ticketVariant.name')->label('نوع بلیط')->placeholder('هر نوع'),
                TextColumn::make('direction')->label('جهت')->badge()->placeholder('بدون محدودیت'),
                TextColumn::make('usage_stage')->label('مرحله')->placeholder('هر مرحله'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
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
