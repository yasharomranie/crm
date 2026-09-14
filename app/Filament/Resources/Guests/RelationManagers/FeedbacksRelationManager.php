<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeedbacksRelationManager extends RelationManager
{
    protected static string $relationship = 'feedbacks';

    protected static ?string $title = 'نظرسنجی‌های پس از خروج (NPS)';

    protected static ?string $modelLabel = 'نظرسنجی';

    protected static ?string $pluralModelLabel = 'نظرسنجی‌ها';

    /**
     * These rows are auto-created on checkout (Booking::checkOut()); staff
     * only fill in the score/comment once the guest actually responds -
     * there's no "create" action here on purpose.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nps_score')
                    ->label('امتیاز NPS (۰ تا ۱۰)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10),
                Textarea::make('comment')
                    ->label('نظر مسافر')
                    ->columnSpanFull(),
                DateTimePicker::make('submitted_at')
                    ->jalali()
                    ->displayFormat('Y/m/d H:i')
                    ->label('زمان ثبت پاسخ')
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comment')
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('requested_at')->label('زمان درخواست')->jalaliDateTime('Y/m/d')->placeholder('—'),
                TextColumn::make('nps_score')->label('امتیاز')->placeholder('در انتظار پاسخ'),
                TextColumn::make('comment')->label('نظر')->limit(40)->placeholder('—'),
                TextColumn::make('submitted_at')->label('زمان پاسخ')->jalaliDateTime('Y/m/d')->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
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
