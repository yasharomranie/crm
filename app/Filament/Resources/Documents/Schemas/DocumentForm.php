<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Enums\DocumentAccessLevel;
use App\Models\Tag;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان سند')
                    ->required()
                    ->maxLength(255),
                Select::make('folder_id')
                    ->label('پوشه')
                    ->relationship('folder', 'name')
                    ->searchable()
                    ->preload(),
                FileUpload::make('file_path')
                    ->label('فایل')
                    ->disk('local')
                    ->directory('documents')
                    ->visibility('private')
                    ->previewable(false)
                    ->openable(false)
                    ->downloadable(false)
                    ->required()
                    ->helperText('برای ثبت نسخهٔ جدید، کافی است فایل را جایگزین کنید؛ فایل قبلی به‌صورت خودکار در تاریخچهٔ نسخه‌ها بایگانی می‌شود.'),
                Select::make('access_level')
                    ->label('سطح دسترسی')
                    ->options(DocumentAccessLevel::class)
                    ->default(DocumentAccessLevel::PublicInternal)
                    ->required()
                    ->live(),
                Select::make('department_id')
                    ->label('دپارتمان مجاز')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get) => $get('access_level') === DocumentAccessLevel::DepartmentOnly)
                    ->visible(fn (Get $get) => $get('access_level') === DocumentAccessLevel::DepartmentOnly)
                    ->helperText('فقط کاربران همین دپارتمان این سند را می‌بینند.'),
                Select::make('tags')
                    ->label('برچسب‌ها')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام برچسب')->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => Tag::create($data)->id)
                    ->columnSpanFull(),
            ]);
    }
}
