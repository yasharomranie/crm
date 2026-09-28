<?php

namespace App\Filament\Resources\SeoContentItems\Tables;

use App\Enums\SeoContentItemStatus;
use App\Models\SeoContentItem;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeoContentItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('project.name')
                    ->label('پروژه')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->wrap()
                    ->limit(60),
                TextColumn::make('target_keyword')
                    ->label('کلمه کلیدی هدف')
                    ->placeholder('—'),
                TextColumn::make('content_type')
                    ->label('نوع محتوا')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextColumn::make('assignee.name')
                    ->label('مسئول')
                    ->placeholder('—'),
                TextColumn::make('due_date')
                    ->label('مهلت')
                    ->date('Y/m/d')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('published_at')
                    ->label('تاریخ انتشار')
                    ->dateTime('Y/m/d')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('seo_project_id')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::pluck('name', 'id')),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(fn () => collect(SeoContentItemStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
            ])
            ->recordActions([
                Action::make('startWriting')
                    ->label('شروع نگارش')
                    ->color('warning')
                    ->visible(fn (SeoContentItem $record) => in_array($record->status, [SeoContentItemStatus::Idea, SeoContentItemStatus::Planned]))
                    ->action(fn (SeoContentItem $record) => $record->update(['status' => SeoContentItemStatus::Writing])),
                Action::make('publish')
                    ->label('انتشار')
                    ->color('success')
                    ->visible(fn (SeoContentItem $record) => $record->status !== SeoContentItemStatus::Published)
                    ->schema([
                        TextInput::make('published_url')
                            ->label('آدرس نهایی منتشرشده')
                            ->url()
                            ->required()
                            ->default(fn (SeoContentItem $record) => $record->published_url),
                    ])
                    ->action(function (array $data, SeoContentItem $record) {
                        $record->update([
                            'status' => SeoContentItemStatus::Published,
                            'published_url' => $data['published_url'],
                            'published_at' => now(),
                        ]);

                        Notification::make()->title('محتوا به‌عنوان منتشرشده علامت‌گذاری شد.')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
