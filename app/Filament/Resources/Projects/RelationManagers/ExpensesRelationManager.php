<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'هزینه‌های واقعی (برای محاسبه AC)';

    protected static ?string $modelLabel = 'هزینه';

    protected static ?string $pluralModelLabel = 'هزینه‌ها';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('شرح هزینه')
                    ->required()
                    ->maxLength(255),
                Select::make('project_phase_id')
                    ->label('مربوط به کدام آیتم WBS')
                    ->relationship('phase', 'title', fn ($query) => $query->where('project_id', $this->getOwnerRecord()->id))
                    ->searchable(),
                TextInput::make('amount')
                    ->label('مبلغ (تومان)')
                    ->numeric()
                    ->required(),
                DatePicker::make('spent_at')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ هزینه')
                    ->default(now())
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('spent_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('شرح'),
                TextColumn::make('phase.title')->label('آیتم WBS')->placeholder('—'),
                TextColumn::make('amount')->label('مبلغ')->numeric()->suffix(' تومان'),
                TextColumn::make('spent_at')->label('تاریخ')->jalaliDate('Y/m/d'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
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
