<?php

namespace App\Filament\Resources\FormTemplates\RelationManagers;

use App\Enums\FormFieldType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'فیلدهای فرم';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('field_key')
                    ->label('کلید فنی فیلد')
                    ->helperText('فقط حروف انگلیسی و زیرخط، بدون فاصله. مثلاً: description')
                    ->required()
                    ->maxLength(255),
                TextInput::make('label')
                    ->label('برچسب نمایشی (فارسی)')
                    ->required()
                    ->maxLength(255),
                Select::make('field_type')
                    ->label('نوع فیلد')
                    ->options(FormFieldType::class)
                    ->required()
                    ->live(),
                KeyValue::make('options')
                    ->label('گزینه‌ها (برای لیست کشویی/انتخاب تکی)')
                    ->visible(fn ($get) => in_array($get('field_type'), [
                        FormFieldType::Select->value,
                        FormFieldType::Radio->value,
                    ]))
                    ->columnSpanFull(),
                Toggle::make('is_required')
                    ->label('اجباری باشد'),
                TextInput::make('sort_order')
                    ->label('ترتیب نمایش')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('ترتیب')->sortable(),
                TextColumn::make('label')->label('برچسب')->searchable(),
                TextColumn::make('field_key')->label('کلید فنی')->fontFamily('mono'),
                TextColumn::make('field_type')->label('نوع'),
                IconColumn::make('is_required')->label('اجباری')->boolean(),
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
