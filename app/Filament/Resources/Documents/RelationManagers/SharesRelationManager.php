<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Explicit per-user sharing, used only by "Restricted" documents
 * (Document::isVisibleTo()) - grants a specific user view/download access
 * beyond the uploader.
 */
class SharesRelationManager extends RelationManager
{
    protected static string $relationship = 'shares';

    protected static ?string $title = 'دسترسی‌های اختصاصی';

    protected static ?string $modelLabel = 'کاربر مجاز';

    protected static ?string $pluralModelLabel = 'کاربران مجاز';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('کاربر')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')->label('کاربر')->searchable(),
                TextColumn::make('created_at')->label('تاریخ اشتراک‌گذاری')->date('Y/m/d'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                DeleteAction::make()->label('لغو دسترسی'),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
