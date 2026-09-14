<?php

namespace App\Filament\Resources\FormTemplates\RelationManagers;

use App\Enums\FormFieldType;
use App\Models\FormField;
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
use Illuminate\Database\Eloquent\Collection;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'فیلدهای فرم';

    protected static ?string $modelLabel = 'فیلد فرم';

    protected static ?string $pluralModelLabel = 'فیلدهای فرم';

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
                        FormFieldType::Select,
                        FormFieldType::Radio,
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
                DeleteAction::make()
                    ->modalDescription(fn (FormField $record) => static::deleteWarning($record->values()->count())),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalDescription(fn (Collection $records) => static::deleteWarning(
                            $records->sum(fn (FormField $record) => $record->values()->count())
                        )),
                ]),
            ]);
    }

    /**
     * form_submission_values.form_field_id is cascadeOnDelete() - deleting
     * a field permanently deletes every submitted answer for it, across
     * every past submission, with no way back. This is the warning that
     * makes that consequence explicit before someone (accidentally) does
     * it - see FormField::values() for why the count is trustworthy.
     */
    private static function deleteWarning(int $valuesCount): string
    {
        if ($valuesCount === 0) {
            return 'این فیلد هیچ پاسخ ثبت‌شده‌ای ندارد و حذف آن بی‌خطر است.';
        }

        return "هشدار: {$valuesCount} پاسخ ثبت‌شده برای این فیلد وجود دارد. حذف فیلد، این پاسخ‌ها را برای همیشه از تمام فرم‌های ثبت‌شده (قبلی) پاک می‌کند و غیرقابل بازگشت است. اگر فقط می‌خواهید فیلد را اصلاح کنید، به‌جای حذف از دکمهٔ ویرایش استفاده کنید.";
    }
}
