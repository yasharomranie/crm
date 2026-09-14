<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectPhaseType;
use App\Models\ProjectPhase;
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

class PhasesRelationManager extends RelationManager
{
    protected static string $relationship = 'phases';

    protected static ?string $title = 'ساختار شکست کار (WBS)';

    protected static ?string $modelLabel = 'آیتم WBS';

    protected static ?string $pluralModelLabel = 'آیتم‌های WBS';

    /**
     * Same-project phases only, excluding the record being edited (a phase
     * can't be its own parent/predecessor).
     */
    protected function siblingOptions(?ProjectPhase $record): array
    {
        return ProjectPhase::query()
            ->where('project_id', $this->getOwnerRecord()->id)
            ->when($record, fn ($query) => $query->whereKeyNot($record->id))
            ->pluck('title', 'id')
            ->toArray();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('نوع')
                    ->options(ProjectPhaseType::class)
                    ->default(ProjectPhaseType::Activity)
                    ->required(),
                Select::make('parent_id')
                    ->label('زیرمجموعه‌ی کدام آیتم است؟')
                    ->options(fn ($record) => $this->siblingOptions($record))
                    ->searchable(),
                Select::make('predecessor_id')
                    ->label('وابسته به اتمام کدام آیتم است؟')
                    ->helperText('برای محاسبه مسیر بحرانی استفاده می‌شود.')
                    ->options(fn ($record) => $this->siblingOptions($record))
                    ->searchable(),
                TextInput::make('weight')
                    ->label('وزن از کل پروژه (٪)')
                    ->helperText('فقط برای آیتم‌های برگ (بدون زیرمجموعه) معنا دارد؛ مجموع وزن‌های برگ باید ۱۰۰ باشد.')
                    ->numeric()
                    ->default(0),
                TextInput::make('progress_percent')
                    ->label('درصد پیشرفت فیزیکی')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0),
                DatePicker::make('start_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ شروع'),
                DatePicker::make('end_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ پایان')
                    ->afterOrEqual('start_date'),
                TextInput::make('sort_order')
                    ->label('ترتیب نمایش')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('type')->label('نوع')->badge(),
                TextColumn::make('parent.title')->label('زیرمجموعه‌ی')->placeholder('—'),
                TextColumn::make('predecessor.title')->label('وابسته به')->placeholder('—'),
                TextColumn::make('weight')->label('وزن')->suffix('٪'),
                TextColumn::make('progress_percent')->label('پیشرفت')->suffix('٪'),
                TextColumn::make('start_date')->label('شروع')->jalaliDate('Y/m/d')->placeholder('—'),
                TextColumn::make('end_date')->label('پایان')->jalaliDate('Y/m/d')->placeholder('—'),
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
