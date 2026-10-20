<?php

namespace App\Filament\Resources\RoomBlocks;

use App\Filament\Resources\RoomBlocks\Pages\ManageRoomBlocks;
use App\Models\RoomBlock;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RoomBlockResource extends Resource
{
    protected static ?string $model = RoomBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|\UnitEnum|null $navigationGroup = 'اقامتی و هتلداری';

    protected static ?string $modelLabel = 'بستن اتاق';

    protected static ?string $pluralModelLabel = 'اتاق‌های خارج از سرویس';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')->label('اتاق')->relationship('room', 'name')->searchable()->preload()->required()->live(),
                DatePicker::make('starts_on')->jalali()->displayFormat('Y/m/d')->label('از تاریخ')->required()->live()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (blank($get('room_id'))) {
                                return;
                            }

                            $to = filled($get('ends_on')) ? Carbon::parse($get('ends_on')) : null;
                            $count = RoomBlock::conflictingBookingsCount((int) $get('room_id'), Carbon::parse($value), $to);

                            if ($count > 0) {
                                $fail("در این بازه {$count} رزرو فعال روی این اتاق هست؛ ابتدا اتاق آن‌ها را عوض یا لغوشان کنید.");
                            }
                        },
                    ]),
                DatePicker::make('ends_on')->jalali()->displayFormat('Y/m/d')->label('تا تاریخ (روز آزادشدن)')
                    ->helperText('خالی = تا اطلاع بعدی. شب‌های بسته‌شده از «از تاریخ» تا شب پیش از این روز است؛ خودِ این روز دوباره قابل رزرو است.')
                    ->after('starts_on')
                    ->live(),
                TextInput::make('reason')->label('دلیل')->helperText('مثلاً «تعمیر کولر» یا «رنگ‌آمیزی»')->required()->maxLength(255)->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('room'))
            ->columns([
                TextColumn::make('room.name')->label('اتاق')->searchable(),
                TextColumn::make('starts_on')->label('از')->jalaliDate('Y/m/d'),
                TextColumn::make('ends_on')->label('تا')->jalaliDate('Y/m/d')->placeholder('تا اطلاع بعدی'),
                TextColumn::make('reason')->label('دلیل')->searchable(),
            ])
            ->defaultSort('starts_on', 'desc')
            ->filters([
                Filter::make('current')->label('فقط جاری و آینده')->default()
                    ->query(fn (Builder $query) => $query->where(fn (Builder $open) => $open->whereNull('ends_on')->orWhereDate('ends_on', '>', today()->toDateString()))),
            ])
            ->recordActions([
                Action::make('backInService')
                    ->label('برگرداندن به سرویس')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('success')
                    ->visible(fn (RoomBlock $record) => $record->isOpenEnded() || $record->ends_on->gt(today()))
                    ->requiresConfirmation()
                    ->modalDescription('از امشب دوباره قابل رزرو می‌شود. اگر وضعیت اتاق «خارج از سرویس» بوده، به «خالی و آماده» برمی‌گردد.')
                    ->action(function (RoomBlock $record): void {
                        $record->endNow();

                        Notification::make()->title('اتاق به سرویس برگشت.')->success()->send();
                    }),
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
            'index' => ManageRoomBlocks::route('/'),
        ];
    }
}
