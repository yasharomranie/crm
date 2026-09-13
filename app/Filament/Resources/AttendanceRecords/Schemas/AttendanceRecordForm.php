<?php

namespace App\Filament\Resources\AttendanceRecords\Schemas;

use App\Enums\AttendanceStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AttendanceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('کاربر')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('work_shift_id')
                    ->label('شیفت')
                    ->relationship('workShift', 'name')
                    ->searchable()
                    ->preload(),
                DatePicker::make('date')
                    ->label('تاریخ')
                    ->required()
                    ->default(now()),
                DateTimePicker::make('check_in_at')
                    ->label('زمان ورود')
                    ->seconds(false),
                DateTimePicker::make('check_out_at')
                    ->label('زمان خروج')
                    ->seconds(false),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(AttendanceStatus::class)
                    ->required(),
                Textarea::make('notes')
                    ->label('یادداشت اصلاحی')
                    ->helperText('در صورت اصلاح دستی رکورد، دلیل را اینجا ثبت کنید.')
                    ->columnSpanFull(),
            ]);
    }
}
