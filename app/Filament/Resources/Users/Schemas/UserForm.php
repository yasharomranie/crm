<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Support\FieldHelp;
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
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('users.name')),
                TextInput::make('mobile')
                    ->label('شماره موبایل')
                    ->tel()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('users.mobile')),
                TextInput::make('national_code')
                    ->label('کد ملی')
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('users.national_code')),
                TextInput::make('email')
                    ->label('ایمیل')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('users.email')),
                TextInput::make('password')
                    ->label('رمز عبور')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->helperText('برای ویرایش، فقط در صورت نیاز به تغییر رمز عبور پر کنید.')
                    ->hintAction(FieldHelp::make('users.password')),
                Select::make('department_id')
                    ->label('دپارتمان')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('users.department_id')),
                Select::make('work_shift_id')
                    ->label('شیفت کاری')
                    ->relationship('workShift', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('برای ثبت حضور و غیاب، یک شیفت کاری به کاربر اختصاص دهید.')
                    ->hintAction(FieldHelp::make('users.work_shift_id')),
                Select::make('roles')
                    ->label('نقش‌ها')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('users.roles')),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->hintAction(FieldHelp::make('users.is_active')),
            ]);
    }
}
