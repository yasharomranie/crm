<?php

namespace App\Filament\Resources\FormTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FormTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان فرم')->searchable(),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('workflowDefinition.name')->label('گردش‌کار تأیید')->placeholder('بدون تأیید'),
                TextColumn::make('version')->label('نسخه')->badge(),
                TextColumn::make('fields_count')->label('تعداد فیلد')->counts('fields'),
                TextColumn::make('submissions_count')->label('تعداد ثبت‌شده')->counts('submissions'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                //
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
