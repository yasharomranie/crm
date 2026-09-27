<?php

namespace App\Filament\Resources\ExternalApiClients\Tables;

use App\Models\ExternalApiClient;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ExternalApiClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client_name')->label('نام اکانت')->searchable(),
                TextColumn::make('company')->label('شرکت')->placeholder('—'),
                TextColumn::make('api_key')->label('API Key')->copyable()->fontFamily('mono'),
                TextColumn::make('creator.name')->label('ثبت به نام')->placeholder('—'),
                TextColumn::make('rate_limit_per_minute')->label('سقف در دقیقه'),
                ToggleColumn::make('is_active')->label('فعال'),
                TextColumn::make('last_used_at')->label('آخرین استفاده')->jalaliDate('Y/m/d H:i')->placeholder('—'),
                TextColumn::make('created_at')->label('تاریخ ساخت')->jalaliDate('Y/m/d'),
            ])
            ->recordActions([
                Action::make('regenerateSecret')
                    ->label('صدور مجدد رمز')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('رمز فعلی این اکانت باطل می‌شود و رمز جدید فقط همین یک‌بار نمایش داده می‌شود. سامانه‌ی بیرونی باید بلافاصله با رمز جدید به‌روزرسانی شود.')
                    ->action(function (ExternalApiClient $record) {
                        $plainSecret = $record->regenerateSecret();

                        Notification::make()
                            ->success()
                            ->title('رمز جدید صادر شد')
                            ->body("این رمز فقط همین یک‌بار نمایش داده می‌شود - همین حالا کپی کنید:\n\n{$plainSecret}")
                            ->persistent()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
