<?php

namespace App\Filament\Resources\TaskChecklistTemplates\RelationManagers;

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

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'آیتم‌های چک‌لیست';

    protected static ?string $modelLabel = 'آیتم چک‌لیست';

    protected static ?string $pluralModelLabel = 'آیتم‌های چک‌لیست';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('عنوان آیتم')
                    ->helperText('مثلاً «جارو و گردگیری» یا «تعویض ملحفه و حوله»')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('ترتیب نمایش')
                    ->numeric()
                    ->default(fn () => $this->getOwnerRecord()->items()->max('sort_order') + 1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('ترتیب')->sortable(),
                TextColumn::make('label')->label('عنوان آیتم'),
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
