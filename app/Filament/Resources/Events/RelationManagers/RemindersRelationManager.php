<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    protected static ?string $title = 'یادآوری‌ها';

    protected static ?string $modelLabel = 'یادآوری';

    protected static ?string $pluralModelLabel = 'یادآوری‌ها';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('days_before')
                    ->label('چند روز قبل از رویداد')
                    ->helperText('برای یادآوری در همان روز رویداد، عدد ۰ را وارد کنید.')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),
                TimePicker::make('remind_time')
                    ->label('ساعت ارسال یادآوری')
                    ->seconds(false)
                    ->default('09:00')
                    ->required(),
                Toggle::make('notify_push')
                    ->label('اعلان فوری (Push)')
                    ->default(true),
                Toggle::make('notify_sms')
                    ->label('پیامک (SMS)')
                    ->default(false),
                Toggle::make('notify_email')
                    ->label('ایمیل')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('days_before')
            ->columns([
                TextColumn::make('days_before')
                    ->label('چند روز قبل')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'همان روز رویداد' : "{$state} روز قبل"),
                TextColumn::make('remind_time')
                    ->label('ساعت ارسال')
                    ->time('H:i'),
                IconColumn::make('notify_push')->label('پوش')->boolean(),
                IconColumn::make('notify_sms')->label('پیامک')->boolean(),
                IconColumn::make('notify_email')->label('ایمیل')->boolean(),
                TextColumn::make('sent_at')
                    ->label('وضعیت ارسال')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'ارسال شد' : 'در انتظار')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        if (! ($data['notify_push'] || $data['notify_sms'] || $data['notify_email'])) {
                            $data['notify_push'] = true;
                        }

                        return $data;
                    }),
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
