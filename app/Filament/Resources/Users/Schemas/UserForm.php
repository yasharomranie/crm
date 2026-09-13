<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام و نام خانوادگی')
                    ->required()
                    ->maxLength(255),
                TextInput::make('mobile')
                    ->label('شماره موبایل')
                    ->tel()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('national_code')
                    ->label('کد ملی')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('ایمیل')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('رمز عبور')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->helperText('برای ویرایش، فقط در صورت نیاز به تغییر رمز عبور پر کنید.'),
                Select::make('department_id')
                    ->label('دپارتمان')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('work_shift_id')
                    ->label('شیفت کاری')
                    ->relationship('workShift', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('برای ثبت حضور و غیاب، یک شیفت کاری به کاربر اختصاص دهید.'),
                Select::make('roles')
                    ->label('نقش‌ها')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }
}
