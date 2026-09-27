<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\EventReminderUnit;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class EventForm
{
    /**
     * The three offsets a "1 week / 24 hours / 1 hour before" reminder
     * plan is built from - a starting point the admin can add with one
     * click and then freely add to, edit, or remove from, not a fixed set
     * every event is limited to.
     *
     * @return array<int, array{value: int, unit: string}>
     */
    private static function standardReminders(): array
    {
        return [
            ['value' => 1, 'unit' => EventReminderUnit::Week->value],
            ['value' => 24, 'unit' => EventReminderUnit::Hour->value],
            ['value' => 1, 'unit' => EventReminderUnit::Hour->value],
        ];
    }

    /**
     * The 'unit' select below has ->options(EventReminderUnit::class),
     * which makes Filament auto-cast its state to an actual enum instance
     * - so by the time the mutateRelationshipData* hooks see it, an
     * item's 'unit' is already an EventReminderUnit, not the raw string
     * EventReminderUnit::from() expects. This accepts either.
     */
    private static function unitFrom(EventReminderUnit|string $value): EventReminderUnit
    {
        return $value instanceof EventReminderUnit ? $value : EventReminderUnit::from($value);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان رویداد')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(3)
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at')
                    ->jalali()
                    ->displayFormat('Y/m/d H:i')
                    ->label('تاریخ و ساعت رویداد')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
                Select::make('participants')
                    ->label('شرکت‌کنندگان (گیرندگان یادآوری)')
                    ->relationship('participants', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('اگر کسی انتخاب نشود، یادآوری‌ها فقط برای ایجادکننده‌ی رویداد ارسال می‌شود.')
                    ->default(fn () => Auth::id() ? [Auth::id()] : []),
                Hidden::make('created_by')
                    ->default(fn () => Auth::id()),

                Repeater::make('reminders')
                    ->relationship()
                    ->label('یادآوری‌ها')
                    ->hintAction(
                        Action::make('addStandardReminders')
                            ->label('افزودن یادآوری‌های استاندارد (۱ هفته، ۲۴ ساعت، ۱ ساعت قبل)')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->color('gray')
                            ->action(function (Repeater $component): void {
                                $items = $component->getRawState() ?? [];

                                $existing = collect($items)
                                    ->map(fn (array $item): string => ($item['value'] ?? '').'-'.($item['unit'] ?? ''))
                                    ->values();

                                foreach (self::standardReminders() as $reminder) {
                                    $key = $reminder['value'].'-'.$reminder['unit'];

                                    if ($existing->contains($key)) {
                                        continue;
                                    }

                                    $uuid = $component->generateUuid();

                                    $items[$uuid ?? count($items)] = [
                                        'value' => $reminder['value'],
                                        'unit' => $reminder['unit'],
                                        'notify_push' => true,
                                        'notify_sms' => false,
                                        'notify_email' => false,
                                    ];
                                }

                                $component->rawState($items);
                                $component->callAfterStateUpdated();
                            }),
                    )
                    ->schema([
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
                    ])
                    ->mutateRelationshipDataBeforeFillUsing(function (array $data): array {
                        $decomposed = EventReminderUnit::decompose($data['minutes_before']);
                        $data['value'] = $decomposed['value'];
                        $data['unit'] = $decomposed['unit']->value;

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        $data['minutes_before'] = ((int) $data['value']) * self::unitFrom($data['unit'])->minutes();

                        if (! ($data['notify_push'] || $data['notify_sms'] || $data['notify_email'])) {
                            $data['notify_push'] = true;
                        }

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                        $data['minutes_before'] = ((int) $data['value']) * self::unitFrom($data['unit'])->minutes();

                        return $data;
                    })
                    ->itemLabel(fn (array $state): ?string => isset($state['value'], $state['unit'])
                        ? "{$state['value']} ".self::unitFrom($state['unit'])->getLabel().' قبل'
                        : null)
                    ->addActionLabel('افزودن یادآوری')
                    ->defaultItems(0)
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
