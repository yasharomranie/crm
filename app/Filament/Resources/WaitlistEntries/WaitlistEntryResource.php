<?php

namespace App\Filament\Resources\WaitlistEntries;

use App\Domain\Hospitality\WaitlistNotifier;
use App\Filament\Resources\WaitlistEntries\Pages\ManageWaitlistEntries;
use App\Models\Guest;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'درخواست در لیست انتظار';

    protected static ?string $pluralModelLabel = 'لیست انتظار اتاق';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guest_id')
                    ->label('مهمان')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام و نام خانوادگی')->required(),
                        TextInput::make('mobile')->label('شماره موبایل')->tel()->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id)
                    ->helperText('پیامک خبر خالی‌شدن اتاق به موبایل همین مهمان می‌رود.'),
                Select::make('room_type_id')
                    ->label('نوع اتاق دلخواه')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('هر نوعی'),
                DatePicker::make('check_in_date')->jalali()->displayFormat('Y/m/d')->label('تاریخ ورود')->required(),
                DatePicker::make('check_out_date')->jalali()->displayFormat('Y/m/d')->label('تاریخ خروج')->required()->afterOrEqual('check_in_date'),
                TextInput::make('adults_count')->label('بزرگسال')->numeric()->minValue(1)->default(2)->required(),
                TextInput::make('children_count')->label('کودک')->numeric()->minValue(0)->default(0),
                Textarea::make('notes')->label('یادداشت')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['guest', 'roomType']))
            ->columns([
                TextColumn::make('guest.name')->label('مهمان')->searchable(),
                TextColumn::make('guest.mobile')->label('موبایل')->placeholder('—'),
                TextColumn::make('roomType.name')->label('نوع اتاق')->placeholder('هر نوعی'),
                TextColumn::make('check_in_date')->label('ورود')->jalaliDate('Y/m/d'),
                TextColumn::make('check_out_date')->label('خروج')->jalaliDate('Y/m/d'),
                TextColumn::make('status')->label('وضعیت')->state(fn (WaitlistEntry $record) => $record->statusLabel())->badge(),
                IconColumn::make('free_now')->label('اتاق الان آزاد')->boolean()
                    ->state(fn (WaitlistEntry $record) => $record->status === WaitlistEntry::STATUS_WAITING && $record->hasFreeRoom()),
                TextColumn::make('notified_at')->label('اطلاع داده شد')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
            ])
            ->defaultSort('check_in_date')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(WaitlistEntry::statusLabels()),
            ])
            ->recordActions([
                Action::make('notifyGuest')
                    ->label('اطلاع بده')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->color('success')
                    ->visible(fn (WaitlistEntry $record) => in_array($record->status, [WaitlistEntry::STATUS_WAITING, WaitlistEntry::STATUS_NOTIFIED], true))
                    ->requiresConfirmation()
                    ->modalDescription(fn (WaitlistEntry $record) => $record->hasFreeRoom() ? 'برای این تاریخ‌ها اتاق آزاد است؛ پیامک به مهمان می‌رود.' : 'توجه: هم‌اکنون برای این تاریخ‌ها اتاق آزادی نیست.')
                    ->action(function (WaitlistEntry $record): void {
                        $sent = app(WaitlistNotifier::class)->notify($record);

                        $notification = Notification::make()->title($sent ? 'پیامک ارسال شد' : 'پیامک ارسال نشد (سرویس پیامک یا شماره‌ی مهمان را بررسی کنید)');

                        ($sent ? $notification->success() : $notification->danger())->send();
                    }),
                Action::make('markBooked')
                    ->label('رزرو شد')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (WaitlistEntry $record) => in_array($record->status, [WaitlistEntry::STATUS_WAITING, WaitlistEntry::STATUS_NOTIFIED], true))
                    ->action(fn (WaitlistEntry $record) => $record->update(['status' => WaitlistEntry::STATUS_BOOKED])),
                Action::make('closeEntry')
                    ->label('بستن')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->visible(fn (WaitlistEntry $record) => in_array($record->status, [WaitlistEntry::STATUS_WAITING, WaitlistEntry::STATUS_NOTIFIED], true))
                    ->requiresConfirmation()
                    ->action(fn (WaitlistEntry $record) => $record->update(['status' => WaitlistEntry::STATUS_CLOSED])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWaitlistEntries::route('/'),
        ];
    }
}
