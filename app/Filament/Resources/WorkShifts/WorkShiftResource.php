<?php

namespace App\Filament\Resources\WorkShifts;

use App\Filament\Resources\WorkShifts\Pages\ManageWorkShifts;
use App\Models\WorkShift;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkShiftResource extends Resource
{
    protected static ?string $model = WorkShift::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'حضور و غیاب';

    protected static ?string $modelLabel = 'شیفت کاری';

    protected static ?string $pluralModelLabel = 'شیفت‌های کاری';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام شیفت')
                    ->helperText('مثلاً «شیفت اداری» یا «شیفت صبح»')
                    ->required()
                    ->maxLength(255),
                TimePicker::make('start_time')
                    ->label('ساعت شروع')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('ساعت پایان')
                    ->seconds(false)
                    ->required(),
                TextInput::make('grace_minutes')
                    ->label('مهلت تأخیر مجاز (دقیقه)')
                    ->numeric()
                    ->default(15)
                    ->required()
                    ->helperText('ورود تا این مقدار دقیقه بعد از ساعت شروع، «به‌موقع» محسوب می‌شود.'),
                Select::make('department_id')
                    ->label('دپارتمان')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('اختیاری - فقط برای دسته‌بندی. اختصاص شیفت به کاربر از پروفایل خودش انجام می‌شود.'),
                Toggle::make('is_default')
                    ->label('شیفت پیش‌فرض')
                    ->helperText('برای کاربران جدیدی که شیفت مشخصی ندارند.'),
                TextInput::make('latitude')
                    ->label('عرض جغرافیایی محل مجاز')
                    ->numeric()
                    ->step('0.0000001')
                    ->helperText('برای گرفتن مختصات، در Google Maps روی محل دفتر/ویلا کلیک راست کنید و عدد اول را کپی کنید.'),
                TextInput::make('longitude')
                    ->label('طول جغرافیایی محل مجاز')
                    ->numeric()
                    ->step('0.0000001')
                    ->helperText('عدد دوم مختصات Google Maps.'),
                TextInput::make('radius_meters')
                    ->label('شعاع مجاز (متر)')
                    ->numeric()
                    ->default(200)
                    ->required()
                    ->helperText('اگر عرض/طول جغرافیایی خالی بماند، این شیفت هیچ محدودیت مکانی ندارد.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام شیفت')->searchable(),
                TextColumn::make('start_time')->label('شروع')->time('H:i'),
                TextColumn::make('end_time')->label('پایان')->time('H:i'),
                TextColumn::make('grace_minutes')->label('مهلت تأخیر')->suffix(' دقیقه'),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('همه'),
                TextColumn::make('users_count')->label('تعداد کاربر')->counts('users'),
                IconColumn::make('is_default')->label('پیش‌فرض')->boolean(),
                IconColumn::make('latitude')
                    ->label('محدودیت مکانی')
                    ->boolean()
                    ->getStateUsing(fn (WorkShift $record) => $record->hasGeofence())
                    ->trueIcon('heroicon-o-map-pin')
                    ->falseIcon('heroicon-o-globe-alt')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkShifts::route('/'),
        ];
    }
}
