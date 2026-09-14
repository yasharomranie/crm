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
                    ->maxLength(255)
                    // Not a DB-level unique() (form_fields no longer has one -
                    // see its migration): FormField soft-deletes, so a plain
                    // unique() would reject reusing a key that only belongs
                    // to a *deleted* field. scopedUnique() queries through
                    // FormField's default (non-trashed) scope, so recreating
                    // a field with the same key as one you just removed
                    // works, while two currently-active fields on the same
                    // template still can't collide.
                    ->scopedUnique(
                        ignoreRecord: true,
                        modifyQueryUsing: fn ($query) => $query->where('form_template_id', $this->getOwnerRecord()->id),
                    ),
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
                Select::make('workflow_step_id')
                    ->label('این فیلد مخصوص کدام مرحله از تأیید است؟ (اختیاری)')
                    ->relationship(
                        'workflowStep',
                        'name',
                        modifyQueryUsing: fn ($query) => $query->where(
                            'workflow_definition_id',
                            $this->getOwnerRecord()->workflow_definition_id,
                        ),
                    )
                    ->searchable()
                    ->preload()
                    // Only makes sense once the form has a workflow to pick
                    // a step from - see WorkflowDefinitionForm/FormTemplateForm.
                    ->visible(fn () => filled($this->getOwnerRecord()->workflow_definition_id))
                    ->helperText('اگر انتخاب کنید، این فیلد موقع ثبت اولیهٔ فرم نمایش داده نمی‌شود؛ به‌جایش، هر کسی که در همان مرحله اقدام (تأیید/رد) می‌کند، آن را پر می‌کند - مثلاً «نظر دفتر فنی» که فقط موقع تأیید در مرحلهٔ دفتر فنی پر می‌شود. اگر خالی بماند، مثل قبل موقع ثبت اولیه پر خواهد شد.')
                    ->columnSpanFull(),
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
                TextColumn::make('workflowStep.name')->label('مرحلهٔ مخصوص')->placeholder('عمومی (ثبت اولیه)'),
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
     * FormField soft-deletes, so this is no longer destructive: removing a
     * field here just hides it from the form builder and from any *new*
     * submission - every existing submission that already answered it
     * keeps showing that answer (see form-submission-detail.blade.php's
     * ->withTrashed()). This still tells the admin the real, honest
     * consequence rather than a generic "are you sure?".
     */
    private static function deleteWarning(int $valuesCount): string
    {
        if ($valuesCount === 0) {
            return 'این فیلد هیچ پاسخ ثبت‌شده‌ای ندارد و حذف آن بی‌خطر است.';
        }

        return "این فیلد {$valuesCount} پاسخ ثبت‌شده دارد. با حذف آن، فیلد از فرم‌های جدید حذف می‌شود اما پاسخ‌های ثبت‌شدهٔ قبلی حفظ می‌شوند و همچنان در مشاهدهٔ سابمیشن‌های قبلی نمایش داده می‌شوند.";
    }
}
