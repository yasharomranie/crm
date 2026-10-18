<?php

namespace App\Filament\Resources\ExternalApiClients\RelationManagers;

use App\Enums\WebhookEventType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Where an integration's webhooks are set up and watched: which events go
 * to which URL, when the last delivery worked, and - if deliveries keep
 * failing - since when and why, so a broken receiver is noticed here
 * instead of silently losing events.
 */
class WebhookSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'webhookSubscriptions';

    protected static ?string $title = 'وب‌هوک‌ها';

    protected static ?string $modelLabel = 'وب‌هوک';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('event_type')->label('رویداد')->options(WebhookEventType::class)->required(),
            TextInput::make('target_url')->label('آدرس دریافت‌کننده')->url()->required()->maxLength(500),
            TextInput::make('signing_secret')
                ->label('کلید امضا (HMAC)')
                ->default(fn () => Str::random(40))
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('دریافت‌کننده با این کلید امضای هدر X-Webhook-Signature را بررسی می‌کند. بعد از ذخیره دیگر نمایش داده نمی‌شود.'),
            Toggle::make('is_active')->label('فعال')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event_type')->label('رویداد')->badge(),
                TextColumn::make('target_url')->label('آدرس')->limit(40),
                IconColumn::make('is_active')->label('فعال')->boolean(),
                TextColumn::make('last_triggered_at')->label('آخرین ارسال موفق')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
                TextColumn::make('consecutive_failures')->label('خطای پیاپی')->badge()->color(fn (int $state) => $state > 0 ? 'danger' : 'success'),
                TextColumn::make('last_error')->label('آخرین خطا')->limit(50)->tooltip(fn ($record) => $record->last_error)->placeholder('—'),
                TextColumn::make('last_failed_at')->label('زمان آخرین خطا')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
