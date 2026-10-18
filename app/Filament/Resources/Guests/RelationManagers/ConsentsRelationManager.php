<?php

namespace App\Filament\Resources\Guests\RelationManagers;

use App\Enums\MessageChannel;
use App\Models\Guest;
use App\Models\GuestConsent;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The guest's marketing-consent history, newest first. It is append-only
 * on purpose (it is the evidence of when and how consent was given or
 * withdrawn), so rows can be added through the action but never edited or
 * deleted. Staff record a decision the guest gave them (by phone, in
 * person); the guest's own choices arrive from the lead form and the
 * customer app.
 */
class ConsentsRelationManager extends RelationManager
{
    protected static string $relationship = 'consents';

    protected static ?string $title = 'رضایت دریافت پیام تبلیغاتی';

    protected static ?string $modelLabel = 'سابقه';

    protected static ?string $pluralModelLabel = 'سوابق رضایت';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('زمان')->jalaliDateTime('Y/m/d H:i'),
                TextColumn::make('channel')->label('کانال')->badge(),
                TextColumn::make('status')
                    ->label('تصمیم')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === GuestConsent::STATUS_GRANTED ? 'رضایت داد' : 'رضایت را لغو کرد')
                    ->color(fn (string $state) => $state === GuestConsent::STATUS_GRANTED ? 'success' : 'danger'),
                TextColumn::make('source')->label('منبع')->formatStateUsing(fn (string $state) => GuestConsent::SOURCES[$state] ?? $state),
                TextColumn::make('note')->label('توضیح')->placeholder('—'),
                TextColumn::make('recorder.name')->label('ثبت‌کننده')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('recordConsent')
                    ->label('ثبت تصمیم مشتری')
                    ->icon('heroicon-o-hand-raised')
                    ->schema([
                        Select::make('channel')->label('کانال')->options([
                            MessageChannel::Sms->value => MessageChannel::Sms->getLabel(),
                            MessageChannel::Email->value => MessageChannel::Email->getLabel(),
                        ])->required(),
                        Select::make('granted')->label('تصمیم مشتری')->options([
                            '1' => 'رضایت داد',
                            '0' => 'رضایت را لغو کرد',
                        ])->required(),
                        TextInput::make('note')->label('توضیح (مثلاً «تلفنی ۱۴۰۵/۰۷/۲۶»)')->maxLength(255),
                    ])
                    ->action(function (array $data): void {
                        /** @var Guest $guest */
                        $guest = $this->getOwnerRecord();

                        $recorded = $guest->recordConsent(
                            MessageChannel::from($data['channel']),
                            (string) $data['granted'] === '1',
                            'staff',
                            $data['note'] ?? null,
                            auth()->id(),
                        );

                        Notification::make()
                            ->title($recorded ? 'ثبت شد' : 'همین تصمیم از قبل ثبت شده بود')
                            ->{$recorded ? 'success' : 'warning'}()
                            ->send();
                    }),
            ]);
    }
}
