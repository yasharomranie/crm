<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Enums\MessageDeliveryStatus;
use App\Models\CustomerMessage;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Everything said to or by this guest on email, SMS and WhatsApp, newest
 * first. Read-only: sending happens from the «ارسال ایمیل» / «ارسال
 * پیامک» buttons on the guest's page.
 */
class CustomerMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'customerMessages';

    protected static ?string $title = 'مکالمات';

    protected static ?string $modelLabel = 'پیام';

    protected static ?string $pluralModelLabel = 'پیام‌ها';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('channel')->label('کانال')->badge(),
                TextColumn::make('direction')->label('جهت'),
                TextColumn::make('subject')->label('موضوع')->placeholder('—')->wrap(),
                TextColumn::make('body')->label('متن')->limit(120)->wrap(),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->tooltip(fn (CustomerMessage $record) => $record->status === MessageDeliveryStatus::Failed ? $record->error : null),
                TextColumn::make('sender.name')->label('ارسال‌کننده')->placeholder('—'),
                TextColumn::make('created_at')->label('زمان')->jalaliDateTime('Y/m/d H:i'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
