<?php

namespace App\Filament\Resources\GuideObjects\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/** The story video of an object: an uploaded MP4 or a link. */
class VideosRelationManager extends RelationManager
{
    protected static string $relationship = 'videos';

    protected static ?string $title = 'ویدیوی داستان';

    protected static ?string $modelLabel = 'ویدیو';

    protected static ?string $pluralModelLabel = 'ویدیوها';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label('عنوان')->required()->maxLength(150)->columnSpanFull(),
                FileUpload::make('path')
                    ->label('فایل ویدیو (MP4)')
                    ->helperText('اگر آپلود روی این هاست به سقف حجم خورد، ویدیو را جای دیگر بگذارید و آدرسش را در کادر پایین بنویسید.')
                    ->disk('public')
                    ->directory('guide/videos')
                    ->visibility('public')
                    ->acceptedFileTypes(['video/mp4'])
                    ->maxSize(204800)
                    ->required(fn (Get $get): bool => blank($get('url'))),
                TextInput::make('url')
                    ->label('یا آدرس ویدیو')
                    ->url()
                    ->maxLength(500)
                    ->required(fn (Get $get): bool => blank($get('path'))),
                FileUpload::make('poster_path')
                    ->label('تصویر پوستر')
                    ->image()
                    ->disk('public')
                    ->directory('guide/posters')
                    ->visibility('public')
                    ->maxSize(4096)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                TextInput::make('duration_seconds')->label('مدت (ثانیه)')->numeric()->integer()->minValue(1)->maxValue(36000),
                Select::make('language')->label('زبان')->options(['fa' => 'فارسی', 'en' => 'انگلیسی', 'ar' => 'عربی'])->default('fa')->required(),
                Toggle::make('is_published')
                    ->label('منتشر شده')
                    ->helperText('تا روشن نباشد، گردشگر این ویدیو را نمی‌بیند.')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('language')->label('زبان')->badge()->color('gray'),
                TextColumn::make('duration_seconds')->label('مدت')->formatStateUsing(fn ($state) => $state ? Number::toPersian((string) $state).' ثانیه' : '—'),
                IconColumn::make('is_published')->label('منتشر')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
