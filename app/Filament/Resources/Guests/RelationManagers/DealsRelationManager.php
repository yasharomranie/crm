<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Enums\DealSource;
use App\Enums\DealStage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DealsRelationManager extends RelationManager
{
    protected static string $relationship = 'deals';

    protected static ?string $title = 'معاملات و فرصت‌های فروش';

    protected static ?string $modelLabel = 'معامله';

    protected static ?string $pluralModelLabel = 'معاملات';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان معامله')
                    ->required()
                    ->maxLength(255),
                Select::make('stage')
                    ->label('مرحله پایپ‌لاین')
                    ->options(DealStage::class)
                    ->default(DealStage::NewLead)
                    ->required(),
                TextInput::make('value')
                    ->label('ارزش تخمینی (تومان)')
                    ->numeric(),
                Select::make('assigned_to')
                    ->label('کارشناس فروش')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('source')
                    ->label('منبع')
                    ->options(DealSource::class)
                    ->default(DealSource::Other),
                DatePicker::make('expected_close_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ تخمینی قطعی‌شدن'),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('stage')->label('مرحله')->badge(),
                TextColumn::make('value')->label('ارزش')->numeric()->suffix(' تومان')->placeholder('—'),
                TextColumn::make('assignee.name')->label('کارشناس')->placeholder('—'),
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
