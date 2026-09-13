<?php

namespace App\Filament\Resources\AttendanceRecords\Schemas;

use App\Enums\AttendanceStatus;
use App\Filament\Support\FieldHelp;
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
                    ->required()
                    ->hintAction(FieldHelp::make('attendance-records.user_id')),
                Select::make('work_shift_id')
                    ->label('شیفت')
                    ->relationship('workShift', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('attendance-records.work_shift_id')),
                DatePicker::make('date')
                    ->label('تاریخ')
                    ->required()
                    ->default(now())
                    ->hintAction(FieldHelp::make('attendance-records.date')),
                DateTimePicker::make('check_in_at')
                    ->label('زمان ورود')
                    ->seconds(false)
                    ->hintAction(FieldHelp::make('attendance-records.check_in_at')),
                DateTimePicker::make('check_out_at')
                    ->label('زمان خروج')
                    ->seconds(false)
                    ->hintAction(FieldHelp::make('attendance-records.check_out_at')),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(AttendanceStatus::class)
                    ->required()
                    ->hintAction(FieldHelp::make('attendance-records.status')),
                Textarea::make('notes')
                    ->label('یادداشت اصلاحی')
                    ->helperText('در صورت اصلاح دستی رکورد، دلیل را اینجا ثبت کنید.')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('attendance-records.notes')),
            ]);
    }
}
