<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Enums\EventReminderUnit;
use App\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RemindersRelationManager extends RelationManager
{
    protected static string $relationship = 'reminders';

    protected static ?string $title = 'یادآوری‌ها';

    protected static ?string $modelLabel = 'یادآوری';

    protected static ?string $pluralModelLabel = 'یادآوری‌ها';

    /**
     * The three offsets a "1 week / 24 hours / 1 hour before" reminder
     * plan is built from - a starting point the admin can add with one
     * click and then freely add to, edit, or remove from, not a fixed set
     * every event is limited to.
     *
     * @return array<int, int>
     */
    private static function standardMinutesBefore(): array
    {
        return [
            EventReminderUnit::Week->minutes() * 1,
            EventReminderUnit::Hour->minutes() * 24,
            EventReminderUnit::Hour->minutes() * 1,
        ];
    }

    /**
     * The 'unit' select has ->options(EventReminderUnit::class), which
     * makes Filament auto-cast its state to an actual enum instance - so
     * by the time mutateDataUsing() sees it, $data['unit'] is already an
     * EventReminderUnit, not the raw string EventReminderUnit::from()
     * expects. This accepts either.
     */
    private static function unitFrom(EventReminderUnit|string $value): EventReminderUnit
    {
        return $value instanceof EventReminderUnit ? $value : EventReminderUnit::from($value);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->label('مقدار')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                Select::make('unit')
                    ->label('واحد زمانی')
                    ->options(EventReminderUnit::class)
                    ->default(EventReminderUnit::Hour->value)
                    ->native(false)
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
            ->recordTitleAttribute('minutes_before')
            ->columns([
                TextColumn::make('minutes_before')
                    ->label('چه مدت قبل از رویداد')
                    ->formatStateUsing(fn ($record): string => $record->label()),
                IconColumn::make('notify_push')->label('پوش')->boolean(),
                IconColumn::make('notify_sms')->label('پیامک')->boolean(),
                IconColumn::make('notify_email')->label('ایمیل')->boolean(),
                TextColumn::make('sent_at')
                    ->label('وضعیت ارسال')
                    ->formatStateUsing(fn (?string $state): string => $state ? 'ارسال شد' : 'در انتظار')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('minutes_before', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('addStandardReminders')
                    ->label('افزودن یادآوری‌های استاندارد (۱ هفته، ۲۴ ساعت، ۱ ساعت قبل)')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->color('gray')
                    ->action(function (): void {
                        /** @var Event $event */
                        $event = $this->getOwnerRecord();

                        $existing = $event->reminders()->pluck('minutes_before');

                        $added = 0;

                        foreach (self::standardMinutesBefore() as $minutesBefore) {
                            if ($existing->contains($minutesBefore)) {
                                continue;
                            }

                            $event->reminders()->create([
                                'minutes_before' => $minutesBefore,
                                'notify_push' => true,
                            ]);

                            $added++;
                        }

                        Notification::make()
                            ->title($added > 0 ? "{$added} یادآوری اضافه شد." : 'همه‌ی یادآوری‌های استاندارد قبلاً ثبت شده بودند.')
                            ->success()
                            ->send();
                    }),
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['minutes_before'] = ((int) $data['value']) * self::unitFrom($data['unit'])->minutes();

                        if (! ($data['notify_push'] || $data['notify_sms'] || $data['notify_email'])) {
                            $data['notify_push'] = true;
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $decomposed = EventReminderUnit::decompose($data['minutes_before']);
                        $data['value'] = $decomposed['value'];
                        $data['unit'] = $decomposed['unit']->value;

                        return $data;
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $data['minutes_before'] = ((int) $data['value']) * self::unitFrom($data['unit'])->minutes();

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
