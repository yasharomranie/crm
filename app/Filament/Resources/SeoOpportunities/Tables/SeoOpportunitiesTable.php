<?php

namespace App\Filament\Resources\SeoOpportunities\Tables;

use App\Domain\Seo\OpportunityOutcomeService;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use App\Models\SeoContentItem;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeoOpportunitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('detected_at', 'desc')
            ->columns([
                TextColumn::make('project.name')
                    ->label('پروژه')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('نوع')
                    ->badge(),
                TextColumn::make('title')
                    ->label('عنوان')
                    ->wrap()
                    ->limit(80),
                TextColumn::make('priority')
                    ->label('اولویت')
                    ->badge(),
                TextColumn::make('confidence')
                    ->label('قطعیت')
                    ->badge(),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextColumn::make('outcome.verdict')
                    ->label('نتیجه')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('detected_at')
                    ->label('تاریخ شناسایی')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('seo_project_id')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::pluck('name', 'id')),
                SelectFilter::make('type')
                    ->label('نوع')
                    ->options(fn () => collect(SeoOpportunityType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
                SelectFilter::make('priority')
                    ->label('اولویت')
                    ->options(fn () => collect(SeoOpportunityPriority::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(fn () => collect(SeoOpportunityStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]))
                    ->default(SeoOpportunityStatus::Detected->value),
            ])
            ->recordActions([
                Action::make('viewEvidence')
                    ->label('مشاهده شواهد')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (SeoOpportunity $record) => $record->title)
                    ->modalContent(fn (SeoOpportunity $record) => view('filament.seo.opportunity-evidence', ['opportunity' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن'),
                Action::make('markReviewed')
                    ->label('بررسی شد')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->visible(fn (SeoOpportunity $record) => $record->status === SeoOpportunityStatus::Detected)
                    ->action(fn (SeoOpportunity $record) => $record->update(['status' => SeoOpportunityStatus::Reviewed])),
                Action::make('markResolved')
                    ->label('برطرف شد')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (SeoOpportunity $record) => in_array($record->status, [SeoOpportunityStatus::Detected, SeoOpportunityStatus::Reviewed]))
                    ->action(function (SeoOpportunity $record, OpportunityOutcomeService $outcomes) {
                        $record->update(['status' => SeoOpportunityStatus::Resolved, 'resolved_at' => now()]);
                        $outcomes->captureBaseline($record);

                        Notification::make()
                            ->title('فرصت به‌عنوان برطرف‌شده علامت‌گذاری شد.')
                            ->body('نتیجه‌ی واقعی این اقدام حدود ۲۸ روز دیگر قابل بررسی خواهد بود.')
                            ->success()
                            ->send();
                    }),
                Action::make('viewOutcome')
                    ->label('نتیجه اقدام')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('gray')
                    ->visible(fn (SeoOpportunity $record) => $record->outcome !== null)
                    ->modalHeading(fn (SeoOpportunity $record) => $record->title)
                    ->modalContent(fn (SeoOpportunity $record) => view('filament.seo.opportunity-outcome', ['outcome' => $record->outcome]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن'),
                Action::make('dismiss')
                    ->label('رد کردن')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (SeoOpportunity $record) => in_array($record->status, [SeoOpportunityStatus::Detected, SeoOpportunityStatus::Reviewed]))
                    ->action(fn (SeoOpportunity $record) => $record->update(['status' => SeoOpportunityStatus::Dismissed])),
                Action::make('planContent')
                    ->label('برنامه‌ریزی محتوا')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('info')
                    ->schema(fn (SeoOpportunity $record) => [
                        TextInput::make('title')
                            ->label('عنوان محتوا')
                            ->default($record->title)
                            ->required()
                            ->maxLength(255),
                        TextInput::make('content_type')
                            ->label('نوع محتوا')
                            ->helperText('مثلاً «مقاله وبلاگ»، «صفحه فرود»، «بروزرسانی محتوای موجود».'),
                        Select::make('assigned_to')
                            ->label('مسئول نگارش')
                            ->options(fn () => User::pluck('name', 'id')),
                        DatePicker::make('due_date')
                            ->label('مهلت انجام')
                            ->jalali()
                            ->displayFormat('Y/m/d'),
                    ])
                    ->action(function (array $data, SeoOpportunity $record) {
                        SeoContentItem::create([
                            'seo_project_id' => $record->seo_project_id,
                            'seo_opportunity_id' => $record->id,
                            'title' => $data['title'],
                            'target_keyword' => $record->subject_query,
                            'content_type' => $data['content_type'],
                            'assigned_to' => $data['assigned_to'] ?? null,
                            'due_date' => $data['due_date'] ?? null,
                        ]);

                        Notification::make()->title('آیتم محتوا در تقویم محتوا ایجاد شد.')->success()->send();
                    }),
            ]);
    }
}
