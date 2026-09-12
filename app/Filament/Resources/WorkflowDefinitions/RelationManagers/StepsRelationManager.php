<?php

namespace App\Filament\Resources\WorkflowDefinitions\RelationManagers;

use App\Enums\ApproverType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'مراحل تأیید';

    protected static ?string $modelLabel = 'مرحله تأیید';

    protected static ?string $pluralModelLabel = 'مراحل تأیید';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('step_order')
                    ->label('ترتیب مرحله')
                    ->numeric()
                    ->required()
                    ->default(fn () => $this->getOwnerRecord()->steps()->max('step_order') + 1),
                TextInput::make('name')
                    ->label('نام مرحله')
                    ->required()
                    ->maxLength(255),
                Select::make('approver_type')
                    ->label('نوع تأییدکننده')
                    ->options(ApproverType::class)
                    ->required()
                    ->live(),
                Select::make('approver_role_id')
                    ->label('نقش تأییدکننده')
                    ->relationship('approverRole', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('approver_type') === ApproverType::Role->value),
                Select::make('approver_user_id')
                    ->label('کاربر تأییدکننده')
                    ->relationship('approverUser', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn ($get) => $get('approver_type') === ApproverType::SpecificUser->value),
                TextInput::make('sla_hours')
                    ->label('مهلت پاسخ‌گویی (ساعت)')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('step_order')
            ->columns([
                TextColumn::make('step_order')->label('ترتیب')->sortable(),
                TextColumn::make('name')->label('نام مرحله'),
                TextColumn::make('approver_type')->label('نوع تأییدکننده'),
                TextColumn::make('sla_hours')->label('مهلت (ساعت)')->placeholder('—'),
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
