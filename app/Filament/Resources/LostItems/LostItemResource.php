<?php

namespace App\Filament\Resources\LostItems;

use App\Domain\Hospitality\LostItemService;
use App\Filament\Resources\LostItems\Pages\ManageLostItems;
use App\Models\LostItem;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;

class LostItemResource extends Resource
{
    protected static ?string $model = LostItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'شیء جامانده';

    protected static ?string $pluralModelLabel = 'اشیای جامانده';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')->label('شیء')->helperText('مثلاً «شارژر موبایل سفید» یا «عینک آفتابی»')->required()->maxLength(255),
                TextInput::make('location')->label('محل پیداشدن')->maxLength(255),
                Select::make('guest_id')->label('مهمان')->relationship('guest', 'name')->searchable()->preload()
                    ->helperText('اگر صاحب شیء معلوم است انتخاب کنید تا بشود پیامک داد.'),
                Select::make('room_id')->label('اتاق')->relationship('room', 'name')->searchable()->preload(),
                DatePicker::make('found_on')->jalali()->displayFormat('Y/m/d')->label('تاریخ پیداشدن')->default(today())->required(),
                Textarea::make('closing_note')->label('یادداشت')->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['guest', 'room']))
            ->columns([
                TextColumn::make('description')->label('شیء')->searchable(),
                TextColumn::make('guest.name')->label('مهمان')->placeholder('نامشخص')->searchable(),
                TextColumn::make('room.name')->label('اتاق')->placeholder('—'),
                TextColumn::make('location')->label('محل')->placeholder('—'),
                TextColumn::make('found_on')->label('تاریخ پیداشدن')->jalaliDate('Y/m/d'),
                TextColumn::make('status')->label('وضعیت')->state(fn (LostItem $record) => $record->statusLabel())->badge(),
            ])
            ->defaultSort('found_on', 'desc')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(LostItem::statusLabels()),
            ])
            ->recordActions([
                Action::make('tellGuest')
                    ->label('اطلاع به مهمان')
                    ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                    ->color('success')
                    ->visible(fn (LostItem $record) => $record->isOpen() && $record->guest_id !== null)
                    ->requiresConfirmation()
                    ->action(function (LostItem $record): void {
                        $sent = app(LostItemService::class)->tellGuest($record, auth()->id());

                        $notification = Notification::make()->title($sent ? 'پیامک ارسال شد' : 'پیامک ارسال نشد (سرویس پیامک یا شماره‌ی مهمان را بررسی کنید)');

                        ($sent ? $notification->success() : $notification->danger())->send();
                    }),
                static::closeAction('markReturned', 'تحویل داده شد', LostItem::STATUS_RETURNED, Heroicon::OutlinedCheckCircle),
                static::closeAction('markDisposed', 'بایگانی / دور ریخته شد', LostItem::STATUS_DISPOSED, Heroicon::OutlinedArchiveBoxXMark)->color('gray'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function closeAction(string $name, string $label, string $status, Heroicon $icon): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->visible(fn (LostItem $record) => $record->isOpen())
            ->modalHeading($label)
            ->schema([
                Textarea::make('note')->label('یادداشت (اختیاری)')->helperText('مثلاً نام تحویل‌گیرنده یا محل بایگانی'),
            ])
            ->action(function (LostItem $record, array $data) use ($status): void {
                try {
                    app(LostItemService::class)->close($record, $status, $data['note'] ?? null);
                } catch (InvalidArgumentException $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLostItems::route('/'),
        ];
    }
}
