<?php

namespace App\Filament\Resources\FormTemplates\Tables;

use App\Models\FormTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class FormTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان فرم')->searchable(),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('workflowDefinition.name')->label('گردش‌کار تأیید')->placeholder('بدون تأیید'),
                TextColumn::make('version')->label('نسخه')->numeric()->badge(),
                TextColumn::make('fields_count')->label('تعداد فیلد')->counts('fields')->numeric(),
                TextColumn::make('submissions_count')->label('تعداد ثبت‌شده')->counts('submissions')->numeric(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // A soft delete never touches form_submissions.form_template_id
                // (that foreign key is only ever checked on a real row
                // deletion), so - unlike before SoftDeletes was added here -
                // this always succeeds even for a template with real
                // submissions; it just moves to the trash (سطل زباله) via
                // the "بدون حذف‌شده‌ها/همه/فقط حذف‌شده‌ها" filter above.
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make()
                    ->action(function (FormTemplate $record) {
                        if ($record->submissions()->exists()) {
                            Notification::make()
                                ->title('این قالب قابل حذف کامل نیست')
                                ->body('برای این قالب سابمیشن ثبت شده وجود دارد؛ برای حفظ اطلاعات ثبت‌شده، حذف کامل مسدود شده است. همان حالت «حذف‌شده» (سطل زباله) کافی است.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->forceDelete();

                        Notification::make()->title('قالب برای همیشه حذف شد.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $blocked = $records->filter(fn (FormTemplate $record) => $record->submissions()->exists());

                            $records->diff($blocked)->each(fn (FormTemplate $record) => $record->forceDelete());

                            if ($blocked->isNotEmpty()) {
                                Notification::make()
                                    ->title("{$blocked->count()} قالب به‌خاطر داشتن سابمیشن ثبت‌شده حذف کامل نشد.")
                                    ->body('همان حالت «حذف‌شده» (سطل زباله) برای آن‌ها کافی است.')
                                    ->warning()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
