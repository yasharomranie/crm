<?php

namespace App\Filament\Resources\SeoKeywordSuggestions\Tables;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoSearchIntent;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
