<?php

namespace App\Filament\Resources\SeoKeywordSuggestions\Tables;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoSearchIntent;
use App\Models\SeoContentItem;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTrackedKeyword;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeoKeywordSuggestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project.name')
                    ->label('پروژه')
                    ->searchable(),
                TextColumn::make('keyword')
                    ->label('کلمه کلیدی پیشنهادی')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('intent')
                    ->label('Intent')
                    ->badge(),
                TextColumn::make('topicCluster.name')
                    ->label('خوشه موضوعی')
                    ->placeholder('—'),
                TextColumn::make('suggested_content_type')
                    ->label('نوع محتوای پیشنهادی')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label('دلیل')
                    ->wrap()
                    ->limit(100),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('seo_project_id')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::pluck('name', 'id')),
                SelectFilter::make('intent')
                    ->label('Intent')
                    ->options(fn () => collect(SeoSearchIntent::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(fn () => collect(SeoKeywordSuggestionStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]))
                    ->default(SeoKeywordSuggestionStatus::New->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('تأیید')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (SeoKeywordSuggestion $record) => $record->status === SeoKeywordSuggestionStatus::New)
                    ->action(function (SeoKeywordSuggestion $record) {
                        $record->update(['status' => SeoKeywordSuggestionStatus::Approved]);

                        Notification::make()->title('پیشنهاد تأیید شد.')->success()->send();
                    }),
                Action::make('reject')
                    ->label('رد کردن')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (SeoKeywordSuggestion $record) => $record->status === SeoKeywordSuggestionStatus::New)
                    ->action(fn (SeoKeywordSuggestion $record) => $record->update(['status' => SeoKeywordSuggestionStatus::Rejected])),
                Action::make('createContentItem')
                    ->label('ایجاد آیتم محتوا')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('info')
                    ->visible(fn (SeoKeywordSuggestion $record) => $record->status === SeoKeywordSuggestionStatus::Approved)
                    ->schema(fn (SeoKeywordSuggestion $record) => [
                        TextInput::make('title')
                            ->label('عنوان محتوا')
                            ->default($record->keyword)
                            ->required()
                            ->maxLength(255),
                        Select::make('assigned_to')
                            ->label('مسئول نگارش')
                            ->options(fn () => User::pluck('name', 'id')),
                        DatePicker::make('due_date')
                            ->label('مهلت انجام')
                            ->jalali()
                            ->displayFormat('Y/m/d'),
                    ])
                    ->action(function (array $data, SeoKeywordSuggestion $record) {
                        SeoContentItem::create([
                            'seo_project_id' => $record->seo_project_id,
                            'keyword_suggestion_id' => $record->id,
                            'title' => $data['title'],
                            'target_keyword' => $record->keyword,
                            'content_type' => $record->suggested_content_type,
                            'assigned_to' => $data['assigned_to'] ?? null,
                            'due_date' => $data['due_date'] ?? null,
                        ]);

                        // So the moment this content goes live, its rank is
                        // already being watched - no separate trip to the
                        // rank tracker to remember to add it.
                        SeoTrackedKeyword::firstOrCreate(
                            ['seo_project_id' => $record->seo_project_id, 'keyword' => $record->keyword],
                            ['is_active' => true],
                        );

                        Notification::make()->title('آیتم محتوا در تقویم محتوا ایجاد شد.')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
